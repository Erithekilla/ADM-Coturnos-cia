<?php

namespace App\Http\Controllers;

use App\Models\ProductSize;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProductSizeController extends Controller
{
    public function index(): JsonResponse
    {
        $data = ProductSize::with(['product'])->paginate(20);
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
        ]);

        $productId = $request->input('product_id') ?: $request->input('produtos_id');
        $size = $request->input('size') ?: $request->input('tamanho');
        $quantity = $request->input('quantity', $request->input('quantidade', 0));

        $request->merge([
            'product_id' => $productId,
            'size' => $size,
            'quantity' => $quantity,
        ]);

        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'size' => 'required|integer',
            'quantity' => 'required|integer|min:0',
        ]);

        $record = ProductSize::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Size created successfully!',
            'data' => $record
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $record = ProductSize::with(['product'])->find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Size not found.'
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
        ]);

        $record = ProductSize::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Size not found.'
            ], 404);
        }

        $productId = $request->input('product_id') ?: $request->input('produtos_id') ?: $record->product_id;
        $size = $request->input('size') ?: $request->input('tamanho') ?: $record->size;
        $quantity = $request->input('quantity', $request->input('quantidade', $record->quantity));

        $record->update([
            'product_id' => $productId,
            'size' => $size,
            'quantity' => $quantity,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Size updated successfully!',
            'data' => $record
        ], 200);
    }

    public function destroy($id): JsonResponse
    {
        $record = ProductSize::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Size not found.'
            ], 404);
        }

        $record->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Size removed successfully!'
        ], 200);
    }
}

