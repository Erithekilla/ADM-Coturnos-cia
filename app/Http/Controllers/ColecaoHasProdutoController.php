<?php

namespace App\Http\Controllers;

use App\Models\ColecaoHasProduto;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ColecaoHasProdutoController extends Controller
{
    /**
     * List all records (paginated or with relations).
     */
    public function index(): JsonResponse
    {
        $data = ColecaoHasProduto::with(['colecao', 'produto'])->paginate(20);
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
            'id_colecao' => 'required|integer|exists:colecao,id_colecao',
            'produtos_id' => 'required|integer|exists:produtos,id',
        ]);

        $record = ColecaoHasProduto::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'ColecaoHasProduto criado com sucesso!',
            'data' => $record
        ], 201);
    }

    /**
     * Display the specified record.
     */
    public function show($id): JsonResponse
    {
        $record = ColecaoHasProduto::with(['colecao', 'produto'])->find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'ColecaoHasProduto não encontrado.'
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
        $record = ColecaoHasProduto::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'ColecaoHasProduto não encontrado.'
            ], 404);
        }

        $validated = $request->validate([
            'id_colecao' => 'required|integer|exists:colecao,id_colecao',
            'produtos_id' => 'required|integer|exists:produtos,id',
        ]);

        $record->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'ColecaoHasProduto atualizado com sucesso!',
            'data' => $record
        ], 200);
    }

    /**
     * Remove the specified record from storage.
     */
    public function destroy($id): JsonResponse
    {
        $record = ColecaoHasProduto::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'ColecaoHasProduto não encontrado.'
            ], 404);
        }

        $record->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'ColecaoHasProduto removido com sucesso!'
        ], 200);
    }
}
