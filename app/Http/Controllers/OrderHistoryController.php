<?php

namespace App\Http\Controllers;

use App\Models\OrderHistory;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OrderHistoryController extends Controller
{
    public function index(): JsonResponse
    {
        $data = OrderHistory::with(['order'])->paginate(20);
        return response()->json([
            'status' => 'success',
            'data' => $data
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $this->decodeHashids($request, [
            'pedido_id' => Order::class,
            'order_id' => Order::class,
        ]);

        $orderId = $request->input('order_id') ?: $request->input('pedido_id');
        $status = $request->input('status', 1);
        $statusDate = $request->input('status_date') ?: $request->input('data_status') ?: now();
        $notes = $request->input('notes') ?: $request->input('observacao');
        $extraInfo = $request->input('extra_info') ?: $request->input('historico_pedidocol');

        $request->merge([
            'order_id' => $orderId,
            'status' => $status,
            'status_date' => $statusDate,
            'notes' => $notes,
            'extra_info' => $extraInfo,
        ]);

        $validated = $request->validate([
            'status' => 'required|integer',
            'order_id' => 'required|integer|exists:orders,id',
            'status_date' => 'required|date',
            'notes' => 'required|string|max:255',
            'extra_info' => 'nullable|string|max:45',
        ]);

        $record = OrderHistory::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Order history created successfully!',
            'data' => $record
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $record = OrderHistory::findByHashid($id, ['order']);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order history not found.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $record
        ], 200);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $this->decodeHashids($request, [
            'pedido_id' => Order::class,
            'order_id' => Order::class,
        ]);

        $record = OrderHistory::findByHashid($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order history not found.'
            ], 404);
        }

        $orderId = $request->input('order_id') ?: $request->input('pedido_id') ?: $record->order_id;
        $status = $request->input('status', $record->status);
        $statusDate = $request->input('status_date') ?: $request->input('data_status') ?: $record->status_date;
        $notes = $request->input('notes') ?: $request->input('observacao') ?: $record->notes;
        $extraInfo = $request->input('extra_info') ?: $request->input('historico_pedidocol') ?: $record->extra_info;

        $record->update([
            'order_id' => $orderId,
            'status' => $status,
            'status_date' => $statusDate,
            'notes' => $notes,
            'extra_info' => $extraInfo,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Order history updated successfully!',
            'data' => $record
        ], 200);
    }

    public function destroy($id): JsonResponse
    {
        $record = OrderHistory::findByHashid($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order history not found.'
            ], 404);
        }

        $record->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Order history removed successfully!'
        ], 200);
    }
}

