<?php

namespace App\Services;

use App\Models\CoturnoEmbalado;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;

class SuperFreteService
{
    protected string $baseUrl;
    protected string $token;

    public function __construct()
    {
        $this->baseUrl = config('services.super_frete.url', 'https://sandbox.superfrete.com/api/v0/calculator');

        $this->token = config('services.super_frete.token');
    }

    public function calcularFreteCoturno(string $cepDestino, string $services = '1,2,17', ?float $valorDeclarado = 0.0): array
    {
        $coturno = new CoturnoEmbalado();

        return $this->calcular([
            'postal_code' => '01153000',
        ], [
            'postal_code' => $cepDestino,
        ], $services, [
            'height' => number_format((float) $coturno->altura, 2, '.', ''),
            'width' => number_format((float) $coturno->largura, 2, '.', ''),
            'length' => number_format((float) $coturno->comprimento, 2, '.', ''),
            'weight' => number_format((float) $coturno->peso, 2, '.', ''),
        ], $valorDeclarado);
    }

    public function calcular(array $from, array $to, string $services, array $package, float $insuranceValue = 0.0): array
    {
        try {
            $response = Http::withToken($this->token)
                ->withUserAgent('SuaAplicacao/1.0 (contato@suaempresa.com.br)')
                ->post("{$this->baseUrl}/calculator", [
                    'from' => $from,
                    'to' => $to,
                    'services' => $services,
                    'options' => [
                        'own_hand' => false,
                        'receipt' => false,
                        'insurance_value' => $insuranceValue,
                        'use_insurance_value' => $insuranceValue > 0,
                    ],
                    'package' => $package,
                ]);

            if ($response->failed()) {
                return [
                    'success' => false,
                    'message' => $response->json('message') ?? 'Erro ao calcular o frete.',
                    'status' => $response->status(),
                ];
            }

            return [
                'success' => true,
                'data' => $response->json(),
            ];

        } catch (RequestException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
