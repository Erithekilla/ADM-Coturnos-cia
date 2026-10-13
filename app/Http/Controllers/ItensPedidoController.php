<?php

namespace App\Http\Controllers;

use App\Models\ItensPedido;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ItensPedidoController extends Controller
{
    public function index(): JsonResponse
    {
        $data = ItensPedido::with(['pedido', 'produto'])->paginate(20);
        return response()->json([
            'status' => 'success',
            'data' => $data
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'quantidade' => 'required|integer|min:1',
            'tamanho' => 'required|integer',
            'preco_unitario' => 'required|numeric',
            'produtos_id' => 'required|integer|exists:produtos,id',
            'pedido_id' => 'required|integer|exists:pedido,id',
        ]);

        $record = ItensPedido::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'ItensPedido criado com sucesso!',
            'data' => $record
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $record = ItensPedido::with(['pedido', 'produto'])->find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'ItensPedido não encontrado.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $record
        ], 200);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $record = ItensPedido::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'ItensPedido não encontrado.'
            ], 404);
        }

        $validated = $request->validate([
            'quantidade' => 'required|integer|min:1',
            'tamanho' => 'required|integer',
            'preco_unitario' => 'required|numeric',
            'produtos_id' => 'required|integer|exists:produtos,id',
            'pedido_id' => 'required|integer|exists:pedido,id',
        ]);

        $record->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'ItensPedido atualizado com sucesso!',
            'data' => $record
        ], 200);
    }

    public function destroy($id): JsonResponse
    {
        $record = ItensPedido::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'ItensPedido não encontrado.'
            ], 404);
        }

        $record->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'ItensPedido removido com sucesso!'
        ], 200);
    }
}

