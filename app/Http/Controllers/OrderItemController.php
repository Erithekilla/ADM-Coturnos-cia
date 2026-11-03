<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OrderItemController extends Controller
{
    public function index(): JsonResponse
    {
        $data = OrderItem::with(['order', 'product'])->paginate(20);
        return response()->json([
            'status' => 'success',
            'data' => $data
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $this->decodeHashids($request, [
            'produtos_id' => Product::class,
            'product_id' => Product::class,
            'pedido_id' => Order::class,
            'order_id' => Order::class,
        ]);

        $productId = $request->input('product_id') ?: $request->input('produtos_id');
        $orderId = $request->input('order_id') ?: $request->input('pedido_id');
        $quantity = $request->input('quantity', $request->input('quantidade', 1));
        $size = $request->input('size') ?: $request->input('tamanho');
        $unitPrice = $request->input('unit_price') ?: $request->input('preco_unitario');

        $request->merge([
            'product_id' => $productId,
            'order_id' => $orderId,
            'quantity' => $quantity,
            'size' => $size,
            'unit_price' => $unitPrice,
        ]);

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'size' => 'required|integer',
            'unit_price' => 'required|numeric',
            'product_id' => 'required|integer|exists:products,id',
            'order_id' => 'required|integer|exists:orders,id',
        ]);

        $record = OrderItem::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Order item created successfully!',
            'data' => $record
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $record = OrderItem::with(['order', 'product'])->find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order item not found.'
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
            'produtos_id' => Product::class,
            'product_id' => Product::class,
            'pedido_id' => Order::class,
            'order_id' => Order::class,
        ]);

        $record = OrderItem::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order item not found.'
            ], 404);
        }

        $productId = $request->input('product_id') ?: $request->input('produtos_id') ?: $record->product_id;
        $orderId = $request->input('order_id') ?: $request->input('pedido_id') ?: $record->order_id;
        $quantity = $request->input('quantity', $request->input('quantidade', $record->quantity));
        $size = $request->input('size') ?: $request->input('tamanho') ?: $record->size;
        $unitPrice = $request->input('unit_price') ?: $request->input('preco_unitario') ?: $record->unit_price;

        $record->update([
            'product_id' => $productId,
            'order_id' => $orderId,
            'quantity' => $quantity,
            'size' => $size,
            'unit_price' => $unitPrice,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Order item updated successfully!',
            'data' => $record
        ], 200);
    }

    public function destroy($id): JsonResponse
    {
        $record = OrderItem::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order item not found.'
            ], 404);
        }

        $record->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Order item removed successfully!'
        ], 200);
    }
}

