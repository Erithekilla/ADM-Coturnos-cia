<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\AuditLogger;

class PedidoObserver
{
    public function created(Order $pedido): void
    {
        AuditLogger::record('ORDER_CREATE', 'orders', $pedido->id, null, [
            'total_amount' => $pedido->total_amount,
            'status'       => $pedido->status,
        ]);
    }

    public function updated(Order $pedido): void
    {
        $changes = $pedido->getChanges();
        if (empty($changes)) {
            return;
        }

        $old = array_intersect_key($pedido->getOriginal(), $changes);
        $acao = array_key_exists('status', $changes) ? 'CHANGE_ORDER_STATUS' : 'ORDER_UPDATE';

        AuditLogger::record($acao, 'orders', $pedido->id, $old, $changes);
    }

    public function deleted(Order $pedido): void
    {
        AuditLogger::record('ORDER_DELETE', 'orders', $pedido->id, ['status' => $pedido->status], null,
            'Pedido removido');
    }
}
