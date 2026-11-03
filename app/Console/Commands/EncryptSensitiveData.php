<?php

namespace App\Console\Commands;

use App\Casts\SafeEncrypted;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cifra (AES-256-CBC) os dados pessoais legados que ainda estão em texto plano
 * e preenche o Blind Index `cpf_hash`.
 *
 * Também serve para a etapa 4 da rotação de chaves:
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

    public function handle(): int
    {
        $table = Schema::hasTable('users') ? 'users' : (Schema::hasTable('usuario') ? 'usuario' : null);

        if (!$table || !Schema::hasColumn($table, 'cpf_hash')) {
            $this->error('Tabela de usuários ou coluna cpf_hash não existe. Rode antes:');
            $this->line('  php artisan migrate');

            return self::FAILURE;
        }

        $possibleFields = [
            'cpf',
            Schema::hasColumn($table, 'phone') ? 'phone' : 'telefone',
            Schema::hasColumn($table, 'birth_date') ? 'birth_date' : 'data_nascimento',
        ];

        $fields = array_values(array_filter($possibleFields, fn ($f) => Schema::hasColumn($table, $f)));
        $reencrypt = (bool) $this->option('recriptografar');
        $dryRun = (bool) $this->option('dry-run');

        $stats = ['linhas' => 0, 'cifrados' => 0, 'recifrados' => 0, 'hashes' => 0, 'falhas' => 0];
        $duplicados = [];

        DB::table($table)->select(array_merge(['id', 'cpf_hash'], $fields))->orderBy('id')
            ->chunk(100, function ($rows) use (&$stats, &$duplicados, $fields, $reencrypt, $dryRun, $table) {
                foreach ($rows as $row) {
                    $stats['linhas']++;
                    $updates = [];

                    $cpfClean = null;

                    foreach ($fields as $field) {
                        $raw = $row->{$field};
                        if ($raw === null || $raw === '') {
                            continue;
                        }

                        $isEncrypted = SafeEncrypted::isEncryptedPayload($raw);

                        if (!$isEncrypted) {
                            $plain = $field === 'cpf' ? (User::cleanCpf($raw) ?? $raw) : $raw;
                            $updates[$field] = Crypt::encryptString($plain);
                            $stats['cifrados']++;

                            if ($field === 'cpf') {
                                $cpfClean = User::cleanCpf($raw);
                            }
                        } elseif ($reencrypt) {
                            try {
                                $plain = Crypt::decryptString($raw);
                                $updates[$field] = Crypt::encryptString($plain);
                                $stats['recifrados']++;

                                if ($field === 'cpf') {
                                    $cpfClean = User::cleanCpf($plain);
                                }
                            } catch (DecryptException $e) {
                                $this->warn("Linha #{$row->id}: falha ao descriptografar {$field} com as chaves disponíveis.");
                                $stats['falhas']++;
                            }
                        } else {
                            if ($field === 'cpf') {
                                try {
                                    $cpfClean = User::cleanCpf(Crypt::decryptString($raw));
                                } catch (DecryptException $e) {
                                    $stats['falhas']++;
                                }
                            }
                        }
                    }

                    if ($cpfClean !== null && $cpfClean !== '') {
                        $hash = User::generateCpfHash($cpfClean);

                        if ($row->cpf_hash !== $hash) {
                            $outro = DB::table($table)->where('cpf_hash', $hash)->where('id', '!=', $row->id)->value('id');
                            if ($outro !== null) {
                                $duplicados[] = "ID #{$row->id} tem o mesmo CPF do ID #{$outro}";
                                $stats['falhas']++;
                            } else {
                                $updates['cpf_hash'] = $hash;
                                $stats['hashes']++;
                            }
                        }
                    }

                    if (!empty($updates) && !$dryRun) {
                        DB::table($table)->where('id', $row->id)->update($updates);
                    }
                }
            });

        $this->info("Linhas processadas: {$stats['linhas']}");
        $this->line(" - Campos cifrados:     {$stats['cifrados']}");
        if ($reencrypt) {
            $this->line(" - Campos recifrados:   {$stats['recifrados']}");
        }
        $this->line(" - Blind indexes novos: {$stats['hashes']}");
        if ($stats['falhas'] > 0) {
            $this->error(" - Falhas/inconsistências: {$stats['falhas']}");
        }

        if (!empty($duplicados)) {
            $this->warn('CPFs duplicados detectados (não indexados):');
            foreach ($duplicados as $d) {
                $this->line("   * {$d}");
            }
        }

        if ($dryRun) {
            $this->warn('Modo --dry-run: nenhuma alteração foi gravada.');
        }

        return $stats['falhas'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
