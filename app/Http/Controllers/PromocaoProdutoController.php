<?php

namespace App\Http\Controllers;

use App\Models\PromocaoProduto;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PromocaoProdutoController extends Controller
{
    public function index(): JsonResponse
    {
        $data = PromocaoProduto::with(['preco'])->paginate(20);
        return response()->json([
            'status' => 'success',
            'data' => $data
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'valor_promo' => 'required|numeric',
            'data_inicio' => 'required|date',
            'data_fim' => 'required|date|after_or_equal:data_inicio',
            'ativo' => 'nullable|string',
            'precos_id_precos' => 'required|integer|exists:precos,id_precos',
        ]);

        $ativoRaw = $request->input('ativo') ?: 'ativo';
        $validated['ativo'] = in_array((string)$ativoRaw, ['0', 'inativo', 'Inativo']) ? 'inativo' : 'ativo';

        $record = PromocaoProduto::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'PromocaoProduto criado com sucesso!',
            'data' => $record
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $record = PromocaoProduto::with(['preco'])->find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'PromocaoProduto não encontrado.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $record
        ], 200);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $record = PromocaoProduto::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'PromocaoProduto não encontrado.'
            ], 404);
        }

        $validated = $request->validate([
            'valor_promo' => 'required|numeric',
            'data_inicio' => 'required|date',
            'data_fim' => 'required|date|after_or_equal:data_inicio',
            'ativo' => 'nullable|string',
            'precos_id_precos' => 'required|integer|exists:precos,id_precos',
        ]);

        if ($request->has('ativo')) {
            $ativoRaw = $request->input('ativo');
            $validated['ativo'] = in_array((string)$ativoRaw, ['0', 'inativo', 'Inativo']) ? 'inativo' : 'ativo';
        }

        $record->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'PromocaoProduto atualizado com sucesso!',
            'data' => $record
        ], 200);
    }

    public function destroy($id): JsonResponse
    {
        $record = PromocaoProduto::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'PromocaoProduto não encontrado.'
            ], 404);
        }

        $record->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'PromocaoProduto removido com sucesso!'
        ], 200);
    }
}

