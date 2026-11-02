<?php

namespace App\Support;

/**
 * Ofuscação criptográfica e reversível de chaves primárias inteiras.
 *
 * Cada ID é cifrado como um único bloco AES-128 (8 bytes do ID + 8 bytes de
 * etiqueta de integridade) usando uma subchave derivada do APP_KEY. A etiqueta
 * depende do "namespace" (nome da tabela), então um ID público de pedido não é
 * aceito como ID de usuário. Qualquer adulteração faz a decodificação falhar,
 * e a aplicação responde 404.
 *
 * Exemplo: 10842 ──▶ "q3Zx9mT0bVa1sLk2Yw8eRA" ──▶ 10842
 */
final class Hashid
{
    private const PUBLIC_ID_PATTERN = '/^[A-Za-z0-9_-]{22}$/';

    /** @var array<string, array{0: string, 1: string}> */
    private static array $derivedKeys = [];

    public static function encode(int $id, string $namespace): string
    {
        [$cipherKey, $tagKey] = self::keysFor(self::currentKey());

        $block = pack('J', $id) . self::tag($namespace, $tagKey);
        $cipher = openssl_encrypt($block, 'aes-128-ecb', $cipherKey, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);

        return rtrim(strtr(base64_encode($cipher), '+/', '-_'), '=');
    }

    /**
     * Devolve o ID inteiro real ou null quando o valor é inválido/adulterado.
     * IDs numéricos crus são rejeitados de propósito (anti-enumeração).
     */
    public static function decode(mixed $publicId, string $namespace): ?int
    {
        if (!is_string($publicId) || !preg_match(self::PUBLIC_ID_PATTERN, $publicId)) {
            return null;
        }

        $cipher = base64_decode(strtr($publicId, '-_', '+/') . '==', true);
        if ($cipher === false || strlen($cipher) !== 16) {
            return null;
        }

        // Aceita IDs emitidos com a chave atual e com as chaves anteriores
        // (APP_PREVIOUS_KEYS), para que links antigos sobrevivam a uma rotação.
        foreach (self::allKeys() as $appKey) {
            [$cipherKey, $tagKey] = self::keysFor($appKey);

            $block = openssl_decrypt($cipher, 'aes-128-ecb', $cipherKey, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);
            if ($block === false || strlen($block) !== 16) {
                continue;
            }

            if (!hash_equals(self::tag($namespace, $tagKey), substr($block, 8, 8))) {
                continue;
            }

            $id = unpack('J', substr($block, 0, 8))[1];

            return $id > 0 ? $id : null;
        }

        return null;
    }

    private static function tag(string $namespace, string $tagKey): string
    {
        return substr(hash_hmac('sha256', 'ns:' . $namespace, $tagKey, true), 0, 8);
    }

    /** @return array{0: string, 1: string} */
    private static function keysFor(string $appKey): array
    {
        if (!isset(self::$derivedKeys[$appKey])) {
            $material = hash_hmac('sha256', 'coturnos:hashid:v1', $appKey, true);
            self::$derivedKeys[$appKey] = [substr($material, 0, 16), substr($material, 16, 16)];
        }

        return self::$derivedKeys[$appKey];
    }

    private static function currentKey(): string
    {
        $key = self::parseKey((string) config('app.key'));

        if ($key === '') {
            throw new \RuntimeException('APP_KEY não configurada: impossível gerar identificadores públicos.');
        }

        return $key;
    }

    /** @return list<string> */
    private static function allKeys(): array
    {
        $previous = array_map(
            fn ($key) => self::parseKey((string) $key),
            (array) config('app.previous_keys', [])
        );

        return array_values(array_filter([self::currentKey(), ...$previous]));
    }

    private static function parseKey(string $key): string
    {
        if (str_starts_with($key, 'base64:')) {
            return (string) base64_decode(substr($key, 7), true);
        }

        return $key;
    }
}
