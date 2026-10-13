<?php

namespace App\Http\Controllers;

use App\Models\ImagensBanner;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ImagensBannerController extends Controller
{
    public function index(): JsonResponse
    {
        $data = ImagensBanner::with(['banner'])->paginate(20);
        return response()->json([
            'status' => 'success',
            'data' => $data
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url_imagem' => 'required|string|max:45',
            'banner_idbanner' => 'required|integer|exists:banner,idbanner',
        ]);

        $record = ImagensBanner::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'ImagensBanner criado com sucesso!',
            'data' => $record
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $record = ImagensBanner::with(['banner'])->find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'ImagensBanner não encontrado.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $record
        ], 200);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $record = ImagensBanner::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'ImagensBanner não encontrado.'
            ], 404);
        }

        $validated = $request->validate([
            'url_imagem' => 'required|string|max:45',
            'banner_idbanner' => 'required|integer|exists:banner,idbanner',
        ]);

        $record->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'ImagensBanner atualizado com sucesso!',
            'data' => $record
        ], 200);
    }

    public function destroy($id): JsonResponse
    {
        $record = ImagensBanner::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'ImagensBanner não encontrado.'
            ], 404);
        }

        $record->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'ImagensBanner removido com sucesso!'
        ], 200);
    }
}

