<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\AuditLogger;

class ProductObserver
{
    public function created(Product $product): void
    {
        AuditLogger::record('PRODUCT_CREATE', 'products', $product->id, null, [
            'model'        => $product->model,
            'product_type' => $product->product_type,
            'brand'        => $product->brand,
        ], 'Product registered: ' . ($product->model ?? $product->id));
    }

    public function updated(Product $product): void
    {
        $changes = collect($product->getChanges())->except(['updated_at'])->all();
        if (empty($changes)) {
            return;
        }

        $old = array_intersect_key($product->getOriginal(), $changes);
        $action = (($changes['is_deleted'] ?? null) === 'S' || ($changes['excluido'] ?? null) === 'S') ? 'PRODUCT_DELETE' : 'PRODUCT_UPDATE';

        AuditLogger::record($action, 'products', $product->id, $old, $changes);
    }

    public function deleted(Product $product): void
    {
        AuditLogger::record('PRODUCT_DELETE', 'products', $product->id, ['model' => $product->model], null,
            'Product removed: ' . ($product->model ?? $product->id));
    }
}

