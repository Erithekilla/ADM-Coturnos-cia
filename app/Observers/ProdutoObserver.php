<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\AuditLogger;

class ProdutoObserver
{
    public function created(Product $produto): void
    {
        AuditLogger::record('PRODUCT_CREATE', 'products', $produto->id, null, [
            'model'        => $produto->model,
            'product_type' => $produto->product_type,
            'brand'        => $produto->brand,
        ], 'Produto cadastrado: ' . ($produto->model ?? $produto->id));
    }

    public function updated(Product $produto): void
    {
        $changes = collect($produto->getChanges())->except(['updated_at'])->all();
        if (empty($changes)) {
            return;
        }

        $old = array_intersect_key($produto->getOriginal(), $changes);
        $acao = (($changes['is_deleted'] ?? null) === 'S' || ($changes['excluido'] ?? null) === 'S') ? 'PRODUCT_DELETE' : 'PRODUCT_UPDATE';

        AuditLogger::record($acao, 'products', $produto->id, $old, $changes);
    }

    public function deleted(Product $produto): void
    {
        AuditLogger::record('PRODUCT_DELETE', 'products', $produto->id, ['model' => $produto->model], null,
            'Produto removido: ' . ($produto->model ?? $produto->id));
    }
}
