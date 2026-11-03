<?php

namespace App\Http\Controllers;

use App\Models\ProductImage;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProductImageController extends Controller
{
    public function index(): JsonResponse
    {
        $data = ProductImage::with(['product'])->paginate(20);
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
        $imageUrl = $request->input('image_url') ?: $request->input('url_imagem');
        $publicId = $request->input('public_id');

        $request->merge([
            'product_id' => $productId,
            'image_url' => $imageUrl,
            'public_id' => $publicId,
        ]);

        $validated = $request->validate([
            'image_url' => 'required|string|max:500',
            'product_id' => 'required|integer|exists:products,id',
            'public_id' => 'nullable|string|max:255',
        ]);

        $record = ProductImage::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Image created successfully!',
            'data' => $record
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $record = ProductImage::with(['product'])->find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Image not found.'
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

        $record = ProductImage::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Image not found.'
            ], 404);
        }

        $productId = $request->input('product_id') ?: $request->input('produtos_id') ?: $record->product_id;
        $imageUrl = $request->input('image_url') ?: $request->input('url_imagem') ?: $record->image_url;
        $publicId = $request->input('public_id') ?: $record->public_id;

        $record->update([
            'image_url' => $imageUrl,
            'product_id' => $productId,
            'public_id' => $publicId,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Image updated successfully!',
            'data' => $record
        ], 200);
    }

    public function destroy($id): JsonResponse
    {
        $record = ProductImage::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Image not found.'
            ], 404);
        }

        $record->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Image removed successfully!'
        ], 200);
    }
}

