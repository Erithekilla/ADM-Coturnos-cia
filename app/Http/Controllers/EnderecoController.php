<?php

namespace App\Http\Controllers;

use App\Models\Endereco;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EnderecoController extends Controller
{
    /**
     * List all records (paginated or with relations).
     */
    public function index(): JsonResponse
    {
        $data = Endereco::with(['usuario'])->paginate(20);
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
            'cep' => 'required|string|max:8',
            'rua' => 'required|string|max:45',
            'bairro' => 'required|string|max:45',
            'cidade' => 'required|string|max:45',
            'estado' => 'required|string|max:45',
            'numero' => 'required|string|max:45',
            'complemento' => 'nullable|string|max:45',
            'padrao' => 'required|integer|in:0,1',
            'usuario_id' => 'required|integer|exists:usuario,id',
        ]);

        $record = Endereco::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Endereco criado com sucesso!',
            'data' => $record
        ], 201);
    }

    /**
     * Display the specified record.
     */
    public function show($id): JsonResponse
    {
        $record = Endereco::with(['usuario'])->find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Endereco não encontrado.'
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
        $record = Endereco::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Endereco não encontrado.'
            ], 404);
        }

        $validated = $request->validate([
            'cep' => 'required|string|max:8',
            'rua' => 'required|string|max:45',
            'bairro' => 'required|string|max:45',
            'cidade' => 'required|string|max:45',
            'estado' => 'required|string|max:45',
            'numero' => 'required|string|max:45',
            'complemento' => 'nullable|string|max:45',
            'padrao' => 'required|integer|in:0,1',
            'usuario_id' => 'required|integer|exists:usuario,id',
        ]);

        $record->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Endereco atualizado com sucesso!',
            'data' => $record
        ], 200);
    }

    /**
     * Remove the specified record from storage.
     */
    public function destroy($id): JsonResponse
    {
        $record = Endereco::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Endereco não encontrado.'
            ], 404);
        }

        $record->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Endereco removido com sucesso!'
        ], 200);
    }
}
