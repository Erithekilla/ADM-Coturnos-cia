<?php

namespace App\Http\Controllers;

use App\Models\Colecao;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ColecaoController extends Controller
{
    /**
     * List all records (paginated or with relations).
     */
    public function index(): JsonResponse
    {
        $data = Colecao::with(['produtos', 'imagens'])->paginate(20);
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
            'nome_colecao' => 'required|string|max:45',
            'descricao_colecao' => 'required|string|max:45',
            'data_inicio' => 'required|date',
            'data_fim' => 'required|date|after_or_equal:data_inicio',
        ]);

        $record = Colecao::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Colecao criado com sucesso!',
            'data' => $record
        ], 201);
    }

    /**
     * Display the specified record.
     */
    public function show($id): JsonResponse
    {
        $record = Colecao::with(['produtos', 'imagens'])->find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Colecao não encontrado.'
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
        $record = Colecao::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Colecao não encontrado.'
            ], 404);
        }

        $validated = $request->validate([
            'nome_colecao' => 'required|string|max:45',
            'descricao_colecao' => 'required|string|max:45',
            'data_inicio' => 'required|date',
            'data_fim' => 'required|date|after_or_equal:data_inicio',
        ]);

        $record->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Colecao atualizado com sucesso!',
            'data' => $record
        ], 200);
    }

    /**
     * Remove the specified record from storage.
     */
    public function destroy($id): JsonResponse
    {
        $record = Colecao::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Colecao não encontrado.'
            ], 404);
        }

        $record->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Colecao removido com sucesso!'
        ], 200);
    }
}
