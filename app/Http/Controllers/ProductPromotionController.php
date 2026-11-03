<?php

namespace App\Http\Controllers;

use App\Models\ProductPromotion;
use App\Models\ProductPrice;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProductPromotionController extends Controller
{
    public function index(): JsonResponse
    {
        $data = ProductPromotion::with(['price'])->paginate(20);
        return response()->json([
            'status' => 'success',
            'data' => $data
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $priceId = $request->input('price_id') ?: $request->input('precos_id_precos');
        $promoPrice = $request->input('promotional_price') ?: $request->input('valor_promo');
        $startDate = $request->input('start_date') ?: $request->input('data_inicio');
        $endDate = $request->input('end_date') ?: $request->input('data_fim');
        $activeRaw = $request->input('active', $request->input('ativo', 1));
        $active = (in_array((string)$activeRaw, ['0', 'inativo', 'Inativo', 'false'])) ? 0 : 1;

        $request->merge([
            'price_id' => $priceId,
            'promotional_price' => $promoPrice,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'active' => $active,
        ]);

        $validated = $request->validate([
            'promotional_price' => 'required|numeric',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'active' => 'nullable|integer|in:0,1',
            'price_id' => 'required|integer|exists:product_prices,id',
        ]);

        $record = ProductPromotion::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Promotion created successfully!',
            'data' => $record
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $record = ProductPromotion::with(['price'])->find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Promotion not found.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $record
        ], 200);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $record = ProductPromotion::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Promotion not found.'
            ], 404);
        }

        $priceId = $request->input('price_id') ?: $request->input('precos_id_precos') ?: $record->price_id;
        $promoPrice = $request->input('promotional_price') ?: $request->input('valor_promo') ?: $record->promotional_price;
        $startDate = $request->input('start_date') ?: $request->input('data_inicio') ?: $record->start_date;
        $endDate = $request->input('end_date') ?: $request->input('data_fim') ?: $record->end_date;

        $active = $record->active;
        if ($request->has('active') || $request->has('ativo')) {
            $activeRaw = $request->input('active', $request->input('ativo'));
            $active = (in_array((string)$activeRaw, ['0', 'inativo', 'Inativo', 'false'])) ? 0 : 1;
        }

        $record->update([
            'price_id' => $priceId,
            'promotional_price' => $promoPrice,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'active' => $active,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Promotion updated successfully!',
            'data' => $record
        ], 200);
    }

    public function destroy($id): JsonResponse
    {
        $record = ProductPromotion::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Promotion not found.'
            ], 404);
        }

        $record->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Promotion removed successfully!'
        ], 200);
    }
}
