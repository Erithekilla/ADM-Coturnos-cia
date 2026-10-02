<?php

namespace App\Http\Controllers;

use App\Models\ImagensColecao;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ImagensColecaoController extends Controller
{
    /**
     * List all records (paginated or with relations).
     */
    public function index(): JsonResponse
    {
        $data = ImagensColecao::with(['colecao'])->paginate(20);
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
            'url_imagem' => 'required|string|max:45',
            'id_colecao' => 'required|integer|exists:colecao,id_colecao',
        ]);

        $record = ImagensColecao::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'ImagensColecao criado com sucesso!',
            'data' => $record
        ], 201);
    }

    /**
     * Display the specified record.
     */
    public function show($id): JsonResponse
    {
        $record = ImagensColecao::with(['colecao'])->find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'ImagensColecao não encontrado.'
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
        $record = ImagensColecao::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'ImagensColecao não encontrado.'
            ], 404);
        }

        $validated = $request->validate([
            'url_imagem' => 'required|string|max:45',
            'id_colecao' => 'required|integer|exists:colecao,id_colecao',
        ]);

        $record->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'ImagensColecao atualizado com sucesso!',
            'data' => $record
        ], 200);
    }

    /**
     * Remove the specified record from storage.
     */
    public function destroy($id): JsonResponse
    {
        $record = ImagensColecao::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'ImagensColecao não encontrado.'
            ], 404);
        }

        $record->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'ImagensColecao removido com sucesso!'
        ], 200);
    }
}
