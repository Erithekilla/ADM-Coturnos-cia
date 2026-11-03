<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function index(): JsonResponse
    {
        $data = Payment::with(['order'])->paginate(20);
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
        $paymentDate = $request->input('payment_date') ?: $request->input('data_pagamento') ?: now();
        $method = $request->input('payment_method') ?: $request->input('metodo_pagamento');
        $status = $request->input('status') ?: 'aprovado';
        $amount = $request->input('amount') ?: $request->input('valor');

        $request->merge([
            'order_id' => $orderId,
            'payment_date' => $paymentDate,
            'payment_method' => $method,
            'status' => $status,
            'amount' => $amount,
        ]);

        $validated = $request->validate([
            'payment_date' => 'required|date',
            'order_id' => 'required|integer|exists:orders,id',
            'payment_method' => 'required|string|max:45',
            'status' => 'required|string|max:45',
            'amount' => 'required|numeric',
        ]);

        $record = Payment::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Payment created successfully!',
            'data' => $record
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $record = Payment::findByHashid($id, ['order']);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Payment not found.'
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

        $record = Payment::findByHashid($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Payment not found.'
            ], 404);
        }

        $orderId = $request->input('order_id') ?: $request->input('pedido_id') ?: $record->order_id;
        $paymentDate = $request->input('payment_date') ?: $request->input('data_pagamento') ?: $record->payment_date;
        $method = $request->input('payment_method') ?: $request->input('metodo_pagamento') ?: $record->payment_method;
        $status = $request->input('status') ?: $record->status;
        $amount = $request->input('amount') ?: $request->input('valor') ?: $record->amount;

        $record->update([
            'payment_date' => $paymentDate,
            'order_id' => $orderId,
            'payment_method' => $method,
            'status' => $status,
            'amount' => $amount,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Payment updated successfully!',
            'data' => $record
        ], 200);
    }

    public function destroy($id): JsonResponse
    {
        $record = Payment::findByHashid($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Payment not found.'
            ], 404);
        }

        $record->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Payment removed successfully!'
        ], 200);
    }
}

