<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Criptografia em repouso AES-256-CBC + HMAC-SHA256 (mesmo formato do cast
 * nativo 'encrypted' do Laravel, compatível com Crypt::decryptString()).
 *
 * Diferença: tolera registros legados ainda em texto plano (antes de rodar
 * `php artisan seguranca:criptografar-dados`), em vez de quebrar a aplicação.
 */
class SafeEncrypted implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (!self::looksEncrypted((string) $value)) {
            return $value; // legado em texto plano
        }

        try {
            return Crypt::decryptString((string) $value);
        } catch (DecryptException) {
            Log::warning("Falha ao descriptografar {$model->getTable()}.{$key} (APP_KEY alterada ou dado corrompido).", [
                'id' => $model->getKey(),
            ]);

            return null;
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value) && self::looksEncrypted($value)) {
            return $value; // já cifrado (ex.: cópia de outro registro)
        }

        return Crypt::encryptString((string) $value);
    }

    public static function looksEncrypted(string $value): bool
    {
        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            return false;
        }

        $payload = json_decode($decoded, true);

        return is_array($payload) && isset($payload['iv'], $payload['value'], $payload['mac']);
    }
}
