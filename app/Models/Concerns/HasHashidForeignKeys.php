<?php

namespace App\Models\Concerns;

/**
 * Codifica, na serialização JSON, as chaves estrangeiras que apontam para
 * modelos com ID ofuscado (ex.: usuario_id, pedido_id, produtos_id).
 *
 * O modelo declara o mapa em hashidForeignKeys(): ['coluna' => Modelo::class].
 */
trait HasHashidForeignKeys
{
    /** @return array<string, class-string> */
    public function hashidForeignKeys(): array
    {
        return [];
    }

    public function toArray()
    {
        return $this->encodeHashidForeignKeys(parent::toArray());
    }

    protected function encodeHashidForeignKeys(array $attributes): array
    {
        foreach ($this->hashidForeignKeys() as $column => $related) {
            if (isset($attributes[$column]) && is_numeric($attributes[$column])) {
                $attributes[$column] = $related::encodeId((int) $attributes[$column]);
            }
        }

        return $attributes;
    }
}
