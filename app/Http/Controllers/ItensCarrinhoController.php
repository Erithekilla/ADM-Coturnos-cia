<?php

namespace App\Http\Controllers;

use App\Models\ItensCarrinho;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ItensCarrinhoController extends Controller
{
    public function index(): JsonResponse
    {
        $data = ItensCarrinho::with(['carrinho', 'produto'])->paginate(20);
        return response()->json([
            'status' => 'success',
            'data' => $data
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'carrinho_id_carrinho' => 'required|integer|exists:carrinho,id_carrinho',
            'produtos_id' => 'required|integer|exists:produtos,id',
            'quantidade' => 'required|integer|min:1',
            'tamanho' => 'required|integer',
        ]);

        $record = ItensCarrinho::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'ItensCarrinho criado com sucesso!',
            'data' => $record
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $record = ItensCarrinho::with(['carrinho', 'produto'])->find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'ItensCarrinho não encontrado.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $record
        ], 200);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $record = ItensCarrinho::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'ItensCarrinho não encontrado.'
            ], 404);
        }

        $validated = $request->validate([
            'carrinho_id_carrinho' => 'required|integer|exists:carrinho,id_carrinho',
            'produtos_id' => 'required|integer|exists:produtos,id',
            'quantidade' => 'required|integer|min:1',
            'tamanho' => 'required|integer',
        ]);

        $record->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'ItensCarrinho atualizado com sucesso!',
            'data' => $record
        ], 200);
    }

    public function destroy($id): JsonResponse
    {
        $record = ItensCarrinho::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'ItensCarrinho não encontrado.'
            ], 404);
        }

        $record->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'ItensCarrinho removido com sucesso!'
        ], 200);
    }
}

