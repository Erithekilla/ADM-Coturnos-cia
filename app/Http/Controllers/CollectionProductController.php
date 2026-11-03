<?php

namespace App\Http\Controllers;

use App\Models\CollectionProduct;
use App\Models\Collection;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CollectionProductController extends Controller
{
    public function index(): JsonResponse
    {
        $data = CollectionProduct::with(['collection', 'product'])->paginate(20);
        return response()->json([
            'status' => 'success',
            'data' => $data
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $this->decodeHashids($request, [
            'collection_id' => Collection::class,
            'id_colecao' => Collection::class,
            'produtos_id' => Product::class,
            'product_id' => Product::class,
        ]);

        $collectionId = $request->input('collection_id') ?: $request->input('id_colecao');
        $productId = $request->input('product_id') ?: $request->input('produtos_id');

        $request->merge([
            'collection_id' => $collectionId,
            'product_id' => $productId,
        ]);

        $validated = $request->validate([
            'collection_id' => 'required|integer|exists:collections,id',
            'product_id' => 'required|integer|exists:products,id',
        ]);

        $record = CollectionProduct::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Product associated with collection successfully!',
            'data' => $record
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $record = CollectionProduct::with(['collection', 'product'])->where('collection_id', Collection::decodeHashid($id) ?? 0)->first();

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Collection product relation not found.'
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
            'collection_id' => Collection::class,
            'id_colecao' => Collection::class,
            'produtos_id' => Product::class,
            'product_id' => Product::class,
        ]);

        $record = CollectionProduct::where('collection_id', Collection::decodeHashid($id) ?? 0)->first();

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Collection product relation not found.'
            ], 404);
        }

        $collectionId = $request->input('collection_id') ?: $request->input('id_colecao') ?: $record->collection_id;
        $productId = $request->input('product_id') ?: $request->input('produtos_id') ?: $record->product_id;

        $record->update([
            'collection_id' => $collectionId,
            'product_id' => $productId,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Collection product relation updated successfully!',
            'data' => $record
        ], 200);
    }

    public function destroy($id): JsonResponse
    {
        $record = CollectionProduct::where('collection_id', Collection::decodeHashid($id) ?? 0)->first();

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Collection product relation not found.'
            ], 404);
        }

        $record->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Collection product relation removed successfully!'
        ], 200);
    }
}

