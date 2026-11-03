<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class ShippingController extends Controller
{
    public function calculate(Request $request): JsonResponse
    {
        $destinationZip = $request->input('destination_zip') ?: $request->input('postal_code') ?: $request->input('cep_destino');
        $originZip = $request->input('origin_zip') ?: $request->input('from_postal_code') ?: $request->input('cep_origem');
        $weight = $request->input('weight') ?: $request->input('peso');
        $height = $request->input('height') ?: $request->input('altura');
        $width = $request->input('width') ?: $request->input('largura');
        $length = $request->input('length') ?: $request->input('comprimento');

        $request->merge([
            'destination_zip' => $destinationZip,
            'origin_zip' => $originZip,
            'weight' => $weight,
            'height' => $height,
            'width' => $width,
            'length' => $length,
        ]);

        $validated = $request->validate([
            'destination_zip' => 'required|string',
            'origin_zip'      => 'nullable|string',
            'weight'          => 'required|numeric',
            'height'          => 'required|numeric',
            'width'           => 'required|numeric',
            'length'          => 'required|numeric',
        ]);

        $response = Http::withToken(config('services.super_frete.token'))
            ->withHeaders([
                'User-Agent' => 'MinhaApp v1.0 (marcelodevcore@gmail.com)',
                'Accept'     => 'application/json',
            ])
            ->post(config('services.super_frete.url'), [
                'from' => ['postal_code' => $validated['origin_zip'] ?? '12510020'],
                'to'   => ['postal_code' => $validated['destination_zip']],
                'services' => '1,2,17',
                'options' => [
                    'own_hand' => false,
                    'receipt' => false,
                    'insurance_value' => 0,
                    'use_insurance_value' => false,
                ],
                'package' => [
                    'weight' => $validated['weight'],
                    'height' => $validated['height'],
                    'width'  => $validated['width'],
                    'length' => $validated['length'],
                ],
            ]);

        if ($response->failed()) {
            return response()->json([
                'error' => 'Erro ao consultar SuperFrete',
                'status' => $response->status(),
                'body' => $response->json(),
            ], $response->status());
        }

        return response()->json($response->json());
    }

    public function calcular(Request $request): JsonResponse
    {
        return $this->calculate($request);
    }
}
