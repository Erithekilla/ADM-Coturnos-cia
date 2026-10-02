<?php

namespace App\Http\Controllers;

use App\Models\ImagensProduto;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ImagensProdutoController extends Controller
{
    /**
     * List all records (paginated or with relations).
     */
    public function index(): JsonResponse
    {
        $data = ImagensProduto::with(['produto'])->paginate(20);
        return response()->json([
            'status' => 'success',
            'data' => $data
        ], 200);
    }

    /**
     * Store a newly created record in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url_imagem' => 'required|string|max:255',
            'produtos_id' => 'required|integer|exists:produtos,id',
        ]);

        $record = ImagensProduto::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'ImagensProduto criado com sucesso!',
            'data' => $record
        ], 201);
    }

    /**
     * Display the specified record.
     */
    public function show($id): JsonResponse
    {
        $record = ImagensProduto::with(['produto'])->find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'ImagensProduto não encontrado.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $record
        ], 200);
    }

    /**
     * Update the specified record in storage.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $record = ImagensProduto::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'ImagensProduto não encontrado.'
            ], 404);
        }

        $validated = $request->validate([
            'url_imagem' => 'required|string|max:255',
            'produtos_id' => 'required|integer|exists:produtos,id',
        ]);

        $record->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'ImagensProduto atualizado com sucesso!',
            'data' => $record
        ], 200);
    }

    /**
     * Remove the specified record from storage.
     */
    public function destroy($id): JsonResponse
    {
        $record = ImagensProduto::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'ImagensProduto não encontrado.'
            ], 404);
        }

        $record->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'ImagensProduto removido com sucesso!'
        ], 200);
    }
}
