<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Grava a trilha de auditoria (2.4) com snapshots old/new sanitizados.
 *
 * Preenche tanto as colunas do padrão documentado (admin_user_id, action,
 * table_affected, record_id, old_values, new_values, ip_address) quanto as
 * colunas legadas usadas pelo painel (autor, acao, tabela, registro_id, ip,
 * detalhes). Só grava colunas que existem de fato na tabela.
 */
class AuditLogger
{
    private const MASK = '***';

    /** Chaves cujo valor nunca deve ir para a auditoria. */
    private const SECRET_KEYS = '/(senha|password|token|secret|remember|cpf|cvv|cvc|card_number|numero_cartao)/i';

    /** Chaves de contato/pessoais que são mascaradas parcialmente. */
    private const CONTACT_KEYS = '/(email|telefone|phone|celular|nascimento|birth)/i';

    private static ?array $columns = null;

    public static function record(
        string $action,
        string $table,
        int|string|null $recordId,
        ?array $old = null,
        ?array $new = null,
        ?string $descricao = null,
        ?User $actor = null,
    ): void {
        $request = request();
        $actor ??= $request?->user() instanceof User ? $request->user() : null;

        $oldClean = $old !== null ? self::sanitize($old) : null;
        $newClean = $new !== null ? self::sanitize($new) : null;
        $ip = $request?->ip();

        $row = [
            // padrão documentado
            'admin_user_id'  => $actor?->getKey(),
            'actor_label'    => $actor ? trim(($actor->first_name ?? $actor->nome ?? 'Usuário') . ' ' . ($actor->last_name ?? $actor->sobrenome ?? '')) . ' (' . self::maskEmail((string) $actor->email) . ')' : 'Sistema',
            'action'         => $action,
            'table_affected' => $table,
            'record_id'      => (int) ($recordId ?? 0),
            'old_values'     => $oldClean,
            'new_values'     => $newClean,
            'description'    => $descricao ?? self::describe($oldClean, $newClean),
            'ip_address'     => $ip,
            'user_agent'     => substr((string) ($request?->userAgent() ?? ''), 0, 255),
            // colunas legadas do painel
            'autor'          => $actor ? trim(($actor->first_name ?? $actor->nome ?? 'Usuário') . ' ' . ($actor->last_name ?? $actor->sobrenome ?? '')) . ' (' . self::maskEmail((string) $actor->email) . ')' : 'Sistema',
            'acao'           => $action,
            'tabela'         => $table,
            'registro_id'    => (int) ($recordId ?? 0),
            'ip'             => $ip,
            'detalhes'       => $descricao ?? self::describe($oldClean, $newClean),
            'created_at'     => now(),
        ];

        $columns = self::columns();
        $row = array_intersect_key($row, array_flip($columns));

        foreach (['old_values', 'new_values'] as $jsonColumn) {
            if (array_key_exists($jsonColumn, $row) && is_array($row[$jsonColumn])) {
                $row[$jsonColumn] = json_encode($row[$jsonColumn], JSON_UNESCAPED_UNICODE);
            }
        }

        if (in_array('updated_at', $columns, true)) {
            $row['updated_at'] = $row['created_at'] ?? now();
        }

        AuditLog::query()->insert($row);
    }

    /**
     * Mascara recursivamente senhas, CPFs, tokens, cartões e dados de contato.
     */
    public static function sanitize(array $data): array
    {
        $clean = [];

        foreach ($data as $key => $value) {
            $keyStr = (string) $key;

            if (is_array($value)) {
                $clean[$key] = self::sanitize($value);
                continue;
            }

            if (preg_match(self::SECRET_KEYS, $keyStr)) {
                $clean[$key] = $value === null || $value === '' ? $value : self::MASK;
                continue;
            }

            if (preg_match(self::CONTACT_KEYS, $keyStr)) {
                $clean[$key] = str_contains($keyStr, 'email') ? self::maskEmail((string) $value) : ($value === null || $value === '' ? $value : self::MASK);
                continue;
            }

            $clean[$key] = is_string($value) ? self::maskFreeText($value) : $value;
        }

        return $clean;
    }

    public static function maskEmail(string $email): string
    {
        if (!str_contains($email, '@')) {
            return $email === '' ? '' : self::MASK;
        }

        [$local, $domain] = explode('@', $email, 2);

        return mb_substr($local, 0, 1) . '***@' . $domain;
    }

    /** Remove números de cartão (13–19 dígitos) e CPFs que apareçam em texto livre. */
    private static function maskFreeText(string $value): string
    {
        $value = preg_replace('/\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/', '***.***.***-**', $value);

        return preg_replace('/\b\d(?:[ -]?\d){12,18}\b/', '**** **** **** ****', $value);
    }

    private static function describe(?array $old, ?array $new): ?string
    {
        if ($new === null) {
            return null;
        }

        $parts = [];
        foreach ($new as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $antes = $old[$key] ?? null;
                $parts[] = $old !== null && array_key_exists($key, $old)
                    ? "{$key}: " . var_export($antes, true) . ' → ' . var_export($value, true)
                    : "{$key}: " . var_export($value, true);
            }
        }

        return $parts ? mb_substr(implode(' | ', $parts), 0, 1000) : null;
    }

    private static function columns(): array
    {
        return self::$columns ??= Schema::getColumnListing((new AuditLog)->getTable());
    }

    /** Usado nos testes quando o schema muda em runtime. */
    public static function flushColumnCache(): void
    {
        self::$columns = null;
    }
}
