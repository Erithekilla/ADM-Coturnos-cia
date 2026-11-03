<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CollectionController extends Controller
{
    public function index(): JsonResponse
    {
        $data = Collection::with(['products', 'images'])->paginate(20);
        return response()->json([
            'status' => 'success',
            'data' => $data
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $name = $request->input('name') ?: $request->input('nome_colecao');
        $desc = $request->input('description') ?: $request->input('descricao_colecao');
        $startDate = $request->input('start_date') ?: $request->input('data_inicio');
        $endDate = $request->input('end_date') ?: $request->input('data_fim');

        $request->merge([
            'name' => $name,
            'description' => $desc,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        $validated = $request->validate([
            'name' => 'required|string|max:45',
            'description' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $record = Collection::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Collection created successfully!',
            'data' => $record
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $record = Collection::with(['products', 'images'])->find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Collection not found.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $record
        ], 200);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $record = Collection::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Collection not found.'
            ], 404);
        }

        $name = $request->input('name') ?: $request->input('nome_colecao') ?: $record->name;
        $desc = $request->input('description') ?: $request->input('descricao_colecao') ?: $record->description;
        $startDate = $request->input('start_date') ?: $request->input('data_inicio') ?: $record->start_date;
        $endDate = $request->input('end_date') ?: $request->input('data_fim') ?: $record->end_date;

        $record->update([
            'name' => $name,
            'description' => $desc,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Collection updated successfully!',
            'data' => $record
        ], 200);
    }

    public function destroy($id): JsonResponse
    {
        $record = Collection::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Collection not found.'
            ], 404);
        }

        $record->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Collection deleted successfully!'
        ], 200);
    }
}
