<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

abstract class Controller
{
    /**
     * Converte IDs públicos (ofuscados) recebidos no corpo da requisição nos
     * IDs inteiros reais, ANTES da validação. Valor inválido/adulterado vira
     * null e a regra `required|integer` devolve 422.
     *
     * @param array<string, class-string> $map ['usuario_id' => Usuario::class]
     */
    protected function decodeHashids(Request $request, array $map): void
    {
        $decoded = [];

        foreach ($map as $field => $model) {
            if ($request->has($field)) {
                $decoded[$field] = $model::decodeHashid($request->input($field));
            }
        }

        if ($decoded) {
            $request->merge($decoded);
        }
    }

    protected function notFound(string $message = 'Recurso não encontrado.'): JsonResponse
    {
        return response()->json(['status' => 'error', 'message' => $message], 404);
    }

    /** Erro 500 sem vazar detalhes internos fora do modo debug. */
    protected function serverError(string $message, \Throwable $e): JsonResponse
    {
        Log::error($message, ['exception' => $e]);

        return response()->json(array_filter([
            'status'       => 'error',
            'message'      => $message,
            'error_detail' => config('app.debug') ? $e->getMessage() : null,
        ]), 500);
    }
}
