<?php

namespace App\Http\Controllers;

use App\Models\CollectionImage;
use App\Models\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CollectionImageController extends Controller
{
    public function index(): JsonResponse
    {
        $data = CollectionImage::with(['collection'])->paginate(20);
        return response()->json([
            'status' => 'success',
            'data' => $data
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $collectionId = $request->input('collection_id') ?: $request->input('id_colecao');
        $imageUrl = $request->input('image_url') ?: $request->input('url_imagem');

        $request->merge([
            'collection_id' => $collectionId,
            'image_url' => $imageUrl,
        ]);

        $validated = $request->validate([
            'image_url' => 'required|string|max:500',
            'collection_id' => 'required|integer|exists:collections,id',
        ]);

        $record = CollectionImage::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Collection image created successfully!',
            'data' => $record
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $record = CollectionImage::with(['collection'])->find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Collection image not found.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $record
        ], 200);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $record = CollectionImage::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Collection image not found.'
            ], 404);
        }

        $collectionId = $request->input('collection_id') ?: $request->input('id_colecao') ?: $record->collection_id;
        $imageUrl = $request->input('image_url') ?: $request->input('url_imagem') ?: $record->image_url;

        $record->update([
            'image_url' => $imageUrl,
            'collection_id' => $collectionId,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Collection image updated successfully!',
            'data' => $record
        ], 200);
    }

    public function destroy($id): JsonResponse
    {
        $record = CollectionImage::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Collection image not found.'
            ], 404);
        }

        $record->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Collection image removed successfully!'
        ], 200);
    }
}

