<?php

namespace App\Observers;

use App\Models\Produto;
use App\Services\AuditLogger;

class ProdutoObserver
{
    public function created(Produto $produto): void
    {
        AuditLogger::record('PRODUCT_CREATE', 'produtos', $produto->id, null, [
            'modelo' => $produto->modelo,
            'tipo'   => $produto->tipo_do_produto,
            'marca'  => $produto->marca,
        ], 'Produto cadastrado: ' . ($produto->modelo ?? $produto->id));
    }

    public function updated(Produto $produto): void
    {
        $changes = collect($produto->getChanges())->except(['updated_at'])->all();
        if (empty($changes)) {
            return;
        }

        $old = array_intersect_key($produto->getOriginal(), $changes);
        $acao = ($changes['excluido'] ?? null) === 'S' ? 'PRODUCT_DELETE' : 'PRODUCT_UPDATE';

        AuditLogger::record($acao, 'produtos', $produto->id, $old, $changes);
    }

    public function deleted(Produto $produto): void
    {
        AuditLogger::record('PRODUCT_DELETE', 'produtos', $produto->id, ['modelo' => $produto->modelo], null,
            'Produto removido: ' . ($produto->modelo ?? $produto->id));
    }
}
