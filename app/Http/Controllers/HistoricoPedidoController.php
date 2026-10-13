<?php

namespace App\Http\Controllers;

use App\Models\HistoricoPedido;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class HistoricoPedidoController extends Controller
{
    public function index(): JsonResponse
    {
        $data = HistoricoPedido::with(['pedido'])->paginate(20);
        return response()->json([
            'status' => 'success',
            'data' => $data
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|integer',
            'pedido_id' => 'required|integer|exists:pedido,id',
            'data_status' => 'required|date',
            'observacao' => 'required|string|max:255',
            'historico_pedidocol' => 'nullable|string|max:45',
        ]);

        $record = HistoricoPedido::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'HistoricoPedido criado com sucesso!',
            'data' => $record
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $record = HistoricoPedido::with(['pedido'])->find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'HistoricoPedido não encontrado.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $record
        ], 200);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $record = HistoricoPedido::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'HistoricoPedido não encontrado.'
            ], 404);
        }

        $validated = $request->validate([
            'status' => 'required|integer',
            'pedido_id' => 'required|integer|exists:pedido,id',
            'data_status' => 'required|date',
            'observacao' => 'required|string|max:255',
            'historico_pedidocol' => 'nullable|string|max:45',
        ]);

        $record->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'HistoricoPedido atualizado com sucesso!',
            'data' => $record
        ], 200);
    }

    public function destroy($id): JsonResponse
    {
        $record = HistoricoPedido::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'HistoricoPedido não encontrado.'
            ], 404);
        }

        $record->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'HistoricoPedido removido com sucesso!'
        ], 200);
    }
}

