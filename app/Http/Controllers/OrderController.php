<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use App\Models\Address;
use App\Models\ProductSize;
use App\Models\OrderHistory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(): JsonResponse
    {
        $orders = Order::with(['user', 'address', 'items.product', 'payments', 'history'])->orderBy('id', 'desc')->get();

        $data = $orders->map(function ($p) {
            return [
                'id' => $p->hashid,
                'totalAmount' => (float) $p->total_amount,
                'valorTotal' => (float) $p->total_amount,
                'status' => $p->status ?: 'Pendente',
                'statusEnvio' => $p->status ?: 'Pendente',
                'statusCompra' => 'Aprovado',
                'dataPedido' => $p->order_date ? date('Y-m-d\TH:i:s', strtotime($p->order_date)) : date('Y-m-d\TH:i:s'),
                'orderDate' => $p->order_date ? date('Y-m-d\TH:i:s', strtotime($p->order_date)) : date('Y-m-d\TH:i:s'),
                'usuarioId' => $p->user_id ? User::encodeId((int) $p->user_id) : null,
                'userId' => $p->user_id ? User::encodeId((int) $p->user_id) : null,
                'cliente' => $p->user ? ($p->user->first_name . ' ' . $p->user->last_name) : 'Cliente removido',
                'enderecoId' => $p->address_id ? Address::encodeId((int) $p->address_id) : null,
                'addressId' => $p->address_id ? Address::encodeId((int) $p->address_id) : null,
                'produtos_do_pedido' => $p->items->map(function ($item) {
                    return [
                        'produto' => [
                            'id' => $item->product?->hashid,
                            'nome' => $item->product?->model ?: 'Calçado removido',
                            'model' => $item->product?->model ?: 'Calçado removido',
                            'categoria' => $item->product?->product_type ?: 'Coturno',
                            'preco' => (float) ($item->unit_price ?? 0),
                            'unitPrice' => (float) ($item->unit_price ?? 0),
                            'status' => 'Em Estoque',
                        ],
                        'quantidade' => $item->quantity ?? 1,
                        'quantity' => $item->quantity ?? 1,
                        'precoUnitario' => (float) ($item->unit_price ?? 0),
                        'unitPrice' => (float) ($item->unit_price ?? 0),
                    ];
                }),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $data
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $this->decodeHashids($request, [
            'usuario_id' => User::class,
            'user_id' => User::class,
            'endereco_id' => Address::class,
            'address_id' => Address::class,
        ]);

        $userId = $request->input('user_id') ?: $request->input('usuario_id');
        $addressId = $request->input('address_id') ?: $request->input('endereco_id');
        $totalAmount = $request->input('total_amount') ?: $request->input('valor_total');
        $orderDate = $request->input('order_date') ?: $request->input('data_pedido') ?: now();
        $status = $request->input('status') ?: 'pendente';

        $request->merge([
            'user_id' => $userId,
            'address_id' => $addressId,
            'total_amount' => $totalAmount,
            'order_date' => $orderDate,
            'status' => $status,
        ]);

        $validated = $request->validate([
            'total_amount' => 'required|numeric',
            'status' => 'required|string|max:45',
            'order_date' => 'required|date',
            'user_id' => 'required|integer|exists:users,id',
            'address_id' => 'required|integer|exists:addresses,id',
        ]);

        $record = Order::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Order created successfully!',
            'data' => $record
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $record = Order::findByHashid($id, ['user', 'address', 'items.product', 'payments', 'history']);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order not found.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $record
        ], 200);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $record = Order::findByHashid($id, ['items']);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order not found.'
            ], 404);
        }

        try {
            DB::beginTransaction();

            $statusAnterior = strtolower(trim((string)$record->status));
            $novoStatusRaw = $request->input('status') ?: $request->input('statusEnvio');

            if ($novoStatusRaw) {
                $novoStatus = strtolower(trim((string)$novoStatusRaw));
                $record->status = $novoStatus;

                if (($novoStatus === 'cancelado' || $novoStatus === 'cancelled') && !in_array($statusAnterior, ['cancelado', 'cancelled'])) {
                    foreach ($record->items as $item) {
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
                    'order_id' => $record->id,
                    'status' => $statusInt,
                    'status_date' => now(),
                    'notes' => "Status updated to '{$novoStatus}'",
                ]);
            }

            $trackingCode = $request->input('tracking_code') ?: $request->input('codigo_rastreio');
            if ($trackingCode) {
                $record->tracking_code = $trackingCode;
            }

            if ($request->has('total_amount') || $request->has('valor_total') || $request->has('valorTotal')) {
                $record->total_amount = (float) ($request->input('total_amount') ?: $request->input('valor_total') ?: $request->input('valorTotal'));
            }

            $record->save();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Order updated successfully!',
                'data' => $record
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->serverError('Error updating order.', $e);
        }
    }

    public function destroy($id): JsonResponse
    {
        $record = Order::findByHashid($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order not found.'
            ], 404);
        }

        $record->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Order removed successfully!'
        ], 200);
    }
}

