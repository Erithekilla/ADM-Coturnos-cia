<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\ProductSize;
use App\Models\OrderHistory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminPedidoController extends Controller
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
        $pedido = Order::findByHashid($id, ['items']);

        if (!$pedido) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pedido não encontrado.'
            ], 404);
        }

        $request->validate([
            'status' => 'required|string',
            'codigo_rastreio' => 'nullable|string|max:100',
            'tracking_code' => 'nullable|string|max:100',
        ]);

        $statusAtual = strtolower(trim((string)$pedido->status));
        $novoStatus = strtolower(trim((string)$request->input('status')));
        $codigoRastreio = $request->input('tracking_code') ?: $request->input('codigo_rastreio');

        if ($statusAtual === $novoStatus) {
            if ($codigoRastreio) {
                $pedido->tracking_code = $codigoRastreio;
                $pedido->save();
            }
            return response()->json([
                'status' => 'success',
                'message' => 'O pedido já se encontra no status ' . $novoStatus,
                'data' => $pedido
            ], 200);
        }

        if (isset(self::ALLOWED_TRANSITIONS[$statusAtual])) {
            $permitidos = self::ALLOWED_TRANSITIONS[$statusAtual];
            if (!in_array($novoStatus, $permitidos)) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Transição de status inválida: de '{$statusAtual}' para '{$novoStatus}'. Transições permitidas: " . implode(', ', $permitidos)
                ], 422);
            }
        }

        if ($novoStatus === 'enviado' || $novoStatus === 'shipped') {
            if (empty($codigoRastreio) && empty($pedido->tracking_code)) {
                return response()->json([
                    'status' => 'error',
                    'message' => "O campo de rastreio (tracking_code) é obrigatório para transitar o pedido para o status 'enviado'."
                ], 422);
            }
        }

        try {
            DB::beginTransaction();

            $pedido->status = $novoStatus;
            if (!empty($codigoRastreio)) {
                $pedido->tracking_code = $codigoRastreio;
            }
            $pedido->save();

            if ($novoStatus === 'cancelado' || $novoStatus === 'cancelled') {
                foreach ($pedido->items as $item) {
                    $query = ProductSize::where('product_id', $item->product_id);
                    if (!empty($item->size)) {
                        $query->where('size', $item->size);
                    }
                    $query->increment('quantity', (int) ($item->quantity ?: 1));
                }
            }

            $statusInt = match ($novoStatus) {
                'cancelado', 'cancelled' => 0,
                'pendente', 'pending' => 1,
                'pago', 'paid' => 2,
                'em_separacao', 'preparing' => 3,
                'enviado', 'shipped' => 4,
                'entregue', 'delivered' => 5,
                default => 1,
            };

            OrderHistory::create([
                'order_id' => $pedido->id,
                'status' => $statusInt,
                'status_date' => now(),
                'notes' => "Status atualizado para '{$novoStatus}'" . ($codigoRastreio ? " (Rastreio: {$codigoRastreio})" : ""),
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "Status do pedido #{$pedido->hashid} atualizado para '{$novoStatus}' com sucesso!" . (in_array($novoStatus, ['cancelado', 'cancelled']) ? " (Estoque estornado automaticamente)." : ""),
                'data' => $pedido->fresh(['items', 'user', 'address'])
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->serverError('Erro ao atualizar status do pedido.', $e);
        }
    }
}
