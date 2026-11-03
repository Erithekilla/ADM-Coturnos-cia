<?php

namespace App\Http\Controllers;

use App\Models\BannerImage;
use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BannerImageController extends Controller
{
    public function index(): JsonResponse
    {
        $data = BannerImage::with(['banner'])->paginate(20);
        return response()->json([
            'status' => 'success',
            'data' => $data
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $bannerId = $request->input('banner_id') ?: $request->input('banner_idbanner');
        $imageUrl = $request->input('image_url') ?: $request->input('url_imagem');

        $request->merge([
            'banner_id' => $bannerId,
            'image_url' => $imageUrl,
        ]);

        $validated = $request->validate([
            'image_url' => 'required|string|max:500',
            'banner_id' => 'required|integer|exists:banners,id',
        ]);

        $record = BannerImage::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Banner image created successfully!',
            'data' => $record
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $record = BannerImage::with(['banner'])->find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Banner image not found.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $record
        ], 200);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $record = BannerImage::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Banner image not found.'
            ], 404);
        }

        $bannerId = $request->input('banner_id') ?: $request->input('banner_idbanner') ?: $record->banner_id;
        $imageUrl = $request->input('image_url') ?: $request->input('url_imagem') ?: $record->image_url;

        $record->update([
            'image_url' => $imageUrl,
            'banner_id' => $bannerId,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Banner image updated successfully!',
            'data' => $record
        ], 200);
    }

    public function destroy($id): JsonResponse
    {
        $record = BannerImage::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Banner image not found.'
            ], 404);
        }

        $record->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Banner image removed successfully!'
        ], 200);
    }
}
