<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BannerController extends Controller
{
    /**
     * List all records (paginated or with relations).
     */
    public function index(): JsonResponse
    {
        $data = Banner::with(['imagens'])->paginate(20);
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
        $ativoRaw = $request->input('ativo') ?: 'ativo';
        $ativo = (in_array((string)$ativoRaw, ['0', 'inativo', 'Inativo'])) ? 'inativo' : 'ativo';

        $record = Banner::create([
            'ativo' => $ativo,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Banner criado com sucesso!',
            'data' => $record
        ], 201);
    }

    /**
     * Display the specified record.
     */
    public function show($id): JsonResponse
    {
        $record = Banner::with(['imagens'])->find($id);

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

    /**
     * Update the specified record in storage.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $record = Banner::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Banner não encontrado.'
            ], 404);
        }

        $ativo = $record->ativo ?: 'ativo';
        if ($request->has('ativo')) {
            $ativoRaw = $request->input('ativo');
            $ativo = (in_array((string)$ativoRaw, ['0', 'inativo', 'Inativo'])) ? 'inativo' : 'ativo';
        }

        $record->update([
            'ativo' => $ativo,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Banner atualizado com sucesso!',
            'data' => $record
        ], 200);
    }

    /**
     * Remove the specified record from storage.
     */
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

    /**
     * Upload e atualização de imagens da loja (Banner, Carrossel e Destaque).
     */
    public function uploadStoreImages(Request $request): JsonResponse
    {
        $urls = [];
        $targetDir = public_path('admin');

        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $fields = [
            'banner' => 'Banner1.png',
            'carousel' => 'bannerc.jpg',
            'category' => 'Coleção.png',
        ];

        foreach ($fields as $field => $defaultFileName) {
            if ($request->hasFile($field)) {
                $file = $request->file($field);
                $ext = $file->getClientOriginalExtension() ?: 'png';
                $savedName = 'upload_' . $field . '_' . time() . '.' . $ext;
                $file->move($targetDir, $savedName);
                $urls[$field] = '/admin/' . $savedName;
            } else {
                $urls[$field] = '/admin/' . $defaultFileName;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Imagens enviadas e processadas com sucesso!',
            'urls' => $urls
        ], 200);
    }
}
