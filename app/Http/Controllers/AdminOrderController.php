<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\ProductSize;
use App\Models\OrderHistory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AdminOrderController extends Controller
{
    private const ALLOWED_TRANSITIONS = [
        'pendente' => ['pago', 'cancelado'],
        'pago' => ['em_separacao', 'cancelado'],
        'em_separacao' => ['enviado', 'cancelado'],
        'enviado' => ['entregue'],
        'entregue' => [],
        'cancelado' => [],
        'pending' => ['paid', 'cancelled'],
        'paid' => ['preparing', 'cancelled'],
        'preparing' => ['shipped', 'cancelled'],
        'shipped' => ['delivered'],
        'delivered' => [],
        'cancelled' => [],
    ];

    public function updateStatus(Request $request, $id): JsonResponse
    {
        $order = Order::findByHashid($id, ['items']);

        if (!$order) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order not found.'
            ], 404);
        }

        $request->validate([
            'status' => 'required|string',
            'codigo_rastreio' => 'nullable|string|max:100',
            'tracking_code' => 'nullable|string|max:100',
        ]);

        $currentStatus = strtolower(trim((string)$order->status));
        $newStatus = strtolower(trim((string)$request->input('status')));
        $trackingCode = $request->input('tracking_code') ?: $request->input('codigo_rastreio');

        if ($currentStatus === $newStatus) {
            if ($trackingCode) {
                $order->tracking_code = $trackingCode;
                $order->save();
            }
            return response()->json([
                'status' => 'success',
                'message' => 'The order is already in status ' . $newStatus,
                'data' => $order
            ], 200);
        }

        if (isset(self::ALLOWED_TRANSITIONS[$currentStatus])) {
            $allowed = self::ALLOWED_TRANSITIONS[$currentStatus];
            if (!in_array($newStatus, $allowed)) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Invalid status transition: from '{$currentStatus}' to '{$newStatus}'. Allowed: " . implode(', ', $allowed)
                ], 422);
            }
        }

        if ($newStatus === 'enviado' || $newStatus === 'shipped') {
            if (empty($trackingCode) && empty($order->tracking_code)) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Tracking code is required to transition order to 'shipped'."
                ], 422);
            }
        }

        try {
            DB::beginTransaction();

            $order->status = $newStatus;
            if (!empty($trackingCode)) {
                $order->tracking_code = $trackingCode;
            }
            $order->save();

            if ($newStatus === 'cancelado' || $newStatus === 'cancelled') {
                foreach ($order->items as $item) {
                    $query = ProductSize::where('product_id', $item->product_id);
                    if (!empty($item->size)) {
                        $query->where('size', $item->size);
                    }
                    $query->increment('quantity', (int) ($item->quantity ?: 1));
                }
            }

            $statusInt = match ($newStatus) {
                'cancelado', 'cancelled' => 0,
                'pendente', 'pending' => 1,
                'pago', 'paid' => 2,
                'em_separacao', 'preparing' => 3,
                'enviado', 'shipped' => 4,
                'entregue', 'delivered' => 5,
                default => 1,
            };

            OrderHistory::create([
                'order_id' => $order->id,
                'status' => $statusInt,
                'status_date' => now(),
                'notes' => "Status updated to '{$newStatus}'" . ($trackingCode ? " (Tracking: {$trackingCode})" : ""),
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "Order #{$order->hashid} status updated to '{$newStatus}' successfully!" . (in_array($newStatus, ['cancelado', 'cancelled']) ? " (Stock refunded)." : ""),
                'data' => $order->fresh(['items', 'user', 'address'])
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->serverError('Error updating order status.', $e);
        }
    }
}
