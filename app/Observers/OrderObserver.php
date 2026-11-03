<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\AuditLogger;

class OrderObserver
{
    public function created(Order $order): void
    {
        AuditLogger::record('ORDER_CREATE', 'orders', $order->id, null, [
            'total_amount' => $order->total_amount,
            'status'       => $order->status,
        ]);
    }

    public function updated(Order $order): void
    {
        $changes = $order->getChanges();
        if (empty($changes)) {
            return;
        }

        $old = array_intersect_key($order->getOriginal(), $changes);
        $action = array_key_exists('status', $changes) ? 'CHANGE_ORDER_STATUS' : 'ORDER_UPDATE';

        AuditLogger::record($action, 'orders', $order->id, $old, $changes);
    }

    public function deleted(Order $order): void
    {
        AuditLogger::record('ORDER_DELETE', 'orders', $order->id, ['status' => $order->status], null,
            'Order deleted');
    }
}

