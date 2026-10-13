<?php

namespace App\Http\Controllers;

use App\Models\TamanhoProduto;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TamanhoProdutoController extends Controller
{
    public function index(): JsonResponse
    {
        $data = TamanhoProduto::with(['produto'])->paginate(20);
        return response()->json([
            'status' => 'success',
            'data' => $data
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'produtos_id' => 'required|integer|exists:produtos,id',
            'tamanho' => 'required|integer',
            'quantidade' => 'required|integer|min:0',
        ]);

        $record = TamanhoProduto::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'TamanhoProduto criado com sucesso!',
            'data' => $record
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $record = TamanhoProduto::with(['produto'])->find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'TamanhoProduto não encontrado.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $record
        ], 200);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $record = TamanhoProduto::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'TamanhoProduto não encontrado.'
            ], 404);
        }

        $validated = $request->validate([
            'produtos_id' => 'required|integer|exists:produtos,id',
            'tamanho' => 'required|integer',
            'quantidade' => 'required|integer|min:0',
        ]);

        $record->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'TamanhoProduto atualizado com sucesso!',
            'data' => $record
        ], 200);
    }

    public function destroy($id): JsonResponse
    {
        $record = TamanhoProduto::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'TamanhoProduto não encontrado.'
            ], 404);
        }

        $record->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'TamanhoProduto removido com sucesso!'
        ], 200);
    }
}

