<?php

namespace App\Http\Controllers;

use App\Models\Pagamento;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PagamentoController extends Controller
{
    /**
     * List all records (paginated or with relations).
     */
    public function index(): JsonResponse
    {
        $data = Pagamento::with(['pedido'])->paginate(20);
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
            'data_pagamento' => 'required|date',
            'pedido_id' => 'required|integer|exists:pedido,id',
            'metodo_pagamento' => 'required|string|max:45',
            'status' => 'required|string|max:45',
            'valor' => 'required|numeric',
        ]);

        $record = Pagamento::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Pagamento criado com sucesso!',
            'data' => $record
        ], 201);
    }

    /**
     * Display the specified record.
     */
    public function show($id): JsonResponse
    {
        $record = Pagamento::with(['pedido'])->find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pagamento não encontrado.'
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
        $record = Pagamento::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pagamento não encontrado.'
            ], 404);
        }

        $validated = $request->validate([
            'data_pagamento' => 'required|date',
            'pedido_id' => 'required|integer|exists:pedido,id',
            'metodo_pagamento' => 'required|string|max:45',
            'status' => 'required|string|max:45',
            'valor' => 'required|numeric',
        ]);

        $record->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Pagamento atualizado com sucesso!',
            'data' => $record
        ], 200);
    }

    /**
     * Remove the specified record from storage.
     */
    public function destroy($id): JsonResponse
    {
        $record = Pagamento::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pagamento não encontrado.'
            ], 404);
        }

        $record->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Pagamento removido com sucesso!'
        ], 200);
    }
}
