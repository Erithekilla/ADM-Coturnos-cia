<?php

namespace App\Observers;

use App\Models\Pedido;
use App\Services\AuditLogger;

class PedidoObserver
{
    public function created(Pedido $pedido): void
    {
        AuditLogger::record('ORDER_CREATE', 'pedido', $pedido->id, null, [
            'valor_total' => $pedido->valor_total,
            'status'      => $pedido->status,
        ]);
    }

    public function updated(Pedido $pedido): void
    {
        $changes = $pedido->getChanges();
        if (empty($changes)) {
            return;
        }

        $old = array_intersect_key($pedido->getOriginal(), $changes);
        $acao = array_key_exists('status', $changes) ? 'CHANGE_ORDER_STATUS' : 'ORDER_UPDATE';

        AuditLogger::record($acao, 'pedido', $pedido->id, $old, $changes);
    }

    public function deleted(Pedido $pedido): void
    {
        AuditLogger::record('ORDER_DELETE', 'pedido', $pedido->id, ['status' => $pedido->status], null,
            'Pedido removido');
    }
}
