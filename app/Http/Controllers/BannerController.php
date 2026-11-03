<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BannerController extends Controller
{
    public function index(): JsonResponse
    {
        $data = Banner::with(['images'])->paginate(20);
        return response()->json([
            'status' => 'success',
            'data' => $data
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $ativoRaw = $request->input('active', $request->input('ativo', 1));
        $ativo = (in_array((string)$ativoRaw, ['0', 'inativo', 'Inativo', 'false'])) ? 0 : 1;

        $record = Banner::create([
            'active' => $ativo,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Banner criado com sucesso!',
            'data' => $record
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $record = Banner::with(['images'])->find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Banner não encontrado.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $record
        ], 200);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $record = Banner::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Banner não encontrado.'
            ], 404);
        }

        $ativo = $record->active;
        if ($request->has('active') || $request->has('ativo')) {
            $ativoRaw = $request->input('active', $request->input('ativo'));
            $ativo = (in_array((string)$ativoRaw, ['0', 'inativo', 'Inativo', 'false'])) ? 0 : 1;
        }

        $record->update([
            'active' => $ativo,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Banner atualizado com sucesso!',
            'data' => $record
        ], 200);
    }

    public function destroy($id): JsonResponse
    {
        $record = Banner::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Banner não encontrado.'
            ], 404);
        }

        $record->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Banner removido com sucesso!'
        ], 200);
    }

    public function uploadStoreImages(Request $request): JsonResponse
    {
        $request->validate([
            'images' => 'required|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        $uploadedPaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $path = $file->store('banners', 'public');
                $uploadedPaths[] = asset('storage/' . $path);
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Imagens do banner salvas com sucesso!',
            'data' => $uploadedPaths
        ], 200);
    }
}
