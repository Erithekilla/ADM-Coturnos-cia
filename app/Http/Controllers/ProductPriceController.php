<?php

namespace App\Http\Controllers;

use App\Models\ProductPrice;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProductPriceController extends Controller
{
    public function index(): JsonResponse
    {
        $data = ProductPrice::with(['product', 'promotions'])->paginate(20);
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
        $price = $request->input('price') ?: $request->input('preco');
        $date = $request->input('effective_date') ?: $request->input('alteracao') ?: now();

        $request->merge([
            'product_id' => $productId,
            'price' => $price,
            'effective_date' => $date,
        ]);

        $validated = $request->validate([
            'price' => 'required|numeric',
            'effective_date' => 'required|date',
            'product_id' => 'required|integer|exists:products,id',
        ]);

        $record = ProductPrice::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Price created successfully!',
            'data' => $record
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $record = ProductPrice::findByHashid($id, ['product', 'promotions']);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Price not found.'
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

        $record = ProductPrice::findByHashid($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Price not found.'
            ], 404);
        }

        $productId = $request->input('product_id') ?: $request->input('produtos_id') ?: $record->product_id;
        $price = $request->input('price') ?: $request->input('preco') ?: $record->price;
        $date = $request->input('effective_date') ?: $request->input('alteracao') ?: $record->effective_date;

        $record->update([
            'price' => $price,
            'effective_date' => $date,
            'product_id' => $productId,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Price updated successfully!',
            'data' => $record
        ], 200);
    }

    public function destroy($id): JsonResponse
    {
        $record = ProductPrice::findByHashid($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Price not found.'
            ], 404);
        }

        $record->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Price removed successfully!'
        ], 200);
    }
}

