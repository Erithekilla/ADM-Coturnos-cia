<?php

namespace App\Http\Controllers;

use App\Models\Preco;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PrecoController extends Controller
{
    /**
     * List all records (paginated or with relations).
     */
    public function index(): JsonResponse
    {
        $data = Preco::with(['produto', 'promocoes'])->paginate(20);
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
            'preco' => 'required|numeric',
            'alteracao' => 'required|date',
            'produtos_id' => 'required|integer|exists:produtos,id',
        ]);

        $record = Preco::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Preco criado com sucesso!',
            'data' => $record
        ], 201);
    }

    /**
     * Display the specified record.
     */
    public function show($id): JsonResponse
    {
        $record = Preco::with(['produto', 'promocoes'])->find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Preco não encontrado.'
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
        $record = Preco::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Preco não encontrado.'
            ], 404);
        }

        $validated = $request->validate([
            'preco' => 'required|numeric',
            'alteracao' => 'required|date',
            'produtos_id' => 'required|integer|exists:produtos,id',
        ]);

        $record->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Preco atualizado com sucesso!',
            'data' => $record
        ], 200);
    }

    /**
     * Remove the specified record from storage.
     */
    public function destroy($id): JsonResponse
    {
        $record = Preco::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Preco não encontrado.'
            ], 404);
        }

        $record->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Preco removido com sucesso!'
        ], 200);
    }
}
