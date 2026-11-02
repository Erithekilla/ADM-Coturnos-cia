<?php

namespace App\Console\Commands;

use App\Casts\SafeEncrypted;
use App\Models\Usuario;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cifra (AES-256-CBC) os dados pessoais legados que ainda estão em texto plano
 * e preenche o Blind Index `cpf_hash`.
 *
 * Também serve para a etapa 4 da rotação de chaves (seção 5):
 *   1. mova a APP_KEY atual para APP_PREVIOUS_KEYS
 *   2. php artisan key:generate
 *   3. php artisan seguranca:criptografar-dados --recriptografar
 *
 * Trabalha direto na tabela (sem eventos do Eloquent) e é idempotente.
 */
class EncryptSensitiveData extends Command
{
    protected $signature = 'seguranca:criptografar-dados
                            {--recriptografar : Recifra também os valores já cifrados com a chave atual (após rotação de APP_KEY)}
                            {--dry-run : Apenas mostra o que seria alterado}';

    protected $description = 'Cifra CPF, telefone e data de nascimento dos usuários e gera o cpf_hash (LGPD)';

    private const FIELDS = ['cpf', 'telefone', 'data_nascimento'];

    public function handle(): int
    {
        if (!Schema::hasColumn('usuario', 'cpf_hash')) {
            $this->error('Coluna usuario.cpf_hash não existe. Rode antes:');
            $this->line('  php artisan migrate --path=database/migrations/2026_10_31_000001_harden_security_columns.php');

            return self::FAILURE;
        }

        $fields = array_values(array_filter(self::FIELDS, fn ($f) => Schema::hasColumn('usuario', $f)));
        $reencrypt = (bool) $this->option('recriptografar');
        $dryRun = (bool) $this->option('dry-run');

        $stats = ['linhas' => 0, 'cifrados' => 0, 'recifrados' => 0, 'hashes' => 0, 'falhas' => 0];
        $duplicados = [];

        DB::table('usuario')->select(array_merge(['id', 'cpf_hash'], $fields))->orderBy('id')
            ->chunkById(200, function ($rows) use ($fields, $reencrypt, $dryRun, &$stats, &$duplicados) {
                foreach ($rows as $row) {
                    $stats['linhas']++;
                    $update = [];
                    $cpfPlano = null;

                    foreach ($fields as $field) {
                        $raw = $row->{$field};
                        if ($raw === null || $raw === '') {
                            continue;
                        }

                        if (SafeEncrypted::looksEncrypted((string) $raw)) {
                            try {
                                $plain = Crypt::decryptString($raw); // tenta APP_KEY e APP_PREVIOUS_KEYS
                            } catch (DecryptException) {
                                $stats['falhas']++;
                                $this->warn("usuario #{$row->id}: não foi possível descriptografar '{$field}' (chave desconhecida).");
                                continue;
                            }

                            if ($reencrypt) {
                                $update[$field] = Crypt::encryptString($plain);
                                $stats['recifrados']++;
                            }
                        } else {
                            $plain = $field === 'cpf' ? (Usuario::cleanCpf($raw) ?? '') : (string) $raw;
                            $update[$field] = Crypt::encryptString($plain);
                            $stats['cifrados']++;
                        }

                        if ($field === 'cpf') {
                            $cpfPlano = $plain;
                        }
                    }

                    if ($cpfPlano !== null) {
                        $hash = Usuario::generateCpfHash($cpfPlano);
                        if ($hash !== $row->cpf_hash) {
                            $conflito = DB::table('usuario')->where('cpf_hash', $hash)->where('id', '!=', $row->id)->value('id');
                            if ($conflito) {
                                $duplicados[] = "#{$row->id} e #{$conflito}";
                            } else {
                                $update['cpf_hash'] = $hash;
                                $stats['hashes']++;
                            }
                        }
                    }

                    if ($update && !$dryRun) {
                        DB::table('usuario')->where('id', $row->id)->update($update);
                    }
                }
            });

        $this->table(array_keys($stats), [array_values($stats)]);

        if ($duplicados) {
            $this->warn('CPFs duplicados encontrados (cpf_hash não preenchido para estes): ' . implode(', ', $duplicados));
        }

        $this->info($dryRun ? 'Simulação concluída: nada foi gravado.' : 'Dados sensíveis protegidos com sucesso.');

        return $stats['falhas'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
