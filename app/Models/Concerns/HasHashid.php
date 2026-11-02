<?php

namespace App\Models\Concerns;

use App\Support\Hashid;

/**
 * Ofuscação reversível da chave primária (Anti-IDOR / Anti-Scraping).
 *
 * - Em respostas JSON a chave primária sai como ID público (string), nunca o inteiro.
 * - findByHashid()/resolveRouteBinding() decodificam o ID público; valor
 *   adulterado ou inteiro cru resulta em null (o controller responde 404).
 */
trait HasHashid
{
    use HasHashidForeignKeys;

    public static function hashidNamespace(): string
    {
        return (new static)->getTable();
    }

    public static function encodeId(int $id): string
    {
        return Hashid::encode($id, static::hashidNamespace());
    }

    public static function decodeHashid(mixed $publicId): ?int
    {
        return Hashid::decode($publicId, static::hashidNamespace());
    }

    public static function findByHashid(mixed $publicId, array $with = []): ?static
    {
        $id = static::decodeHashid($publicId);

        if ($id === null) {
            return null;
        }

        return static::query()->with($with)->find($id);
    }

    public function getHashidAttribute(): ?string
    {
        $key = $this->getKey();

        return $key === null ? null : static::encodeId((int) $key);
    }

    public function getRouteKey(): mixed
    {
        return $this->hashid;
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $id = static::decodeHashid($value);

        return $id === null ? null : $this->where($this->getKeyName(), $id)->first();
    }

    public function toArray()
    {
        $attributes = $this->encodeHashidForeignKeys(parent::toArray());

        $keyName = $this->getKeyName();
        if (isset($attributes[$keyName]) && is_numeric($attributes[$keyName])) {
            $attributes[$keyName] = static::encodeId((int) $attributes[$keyName]);
        }

        return $attributes;
    }
}
