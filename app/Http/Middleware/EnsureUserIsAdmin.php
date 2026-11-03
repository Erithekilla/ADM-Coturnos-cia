<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guarda das rotas administrativas — 4 barreiras obrigatórias e sequenciais:
 * 1. token válido (401) · 2. conta ativa e não excluída (403)
 * 3. role === 'admin' (403) · 4. token com ability 'admin' (403)
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // 1. Rejeita requisições não autenticadas
        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Não autenticado. Credenciais ausentes ou inválidas.',
                'code'    => 'UNAUTHENTICATED',
            ], 401);
        }

        // 2. Bloqueia contas inativas ou excluídas logicamente
        if (!$user->isActive()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Conta suspensa, inativa ou desativada pelo sistema.',
                'code'    => 'ACCOUNT_DISABLED',
            ], 403);
        }

        // 3. Validação estrita do papel administrativo
        if (!$user->isAdmin()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Acesso proibido. Apenas administradores possuem autorização para executar esta ação.',
                'code'    => 'ADMIN_REQUIRED',
            ], 403);
        }

        // 4. Verificação de Token Ability (Sanctum)
        if (method_exists($user, 'currentAccessToken') && $user->currentAccessToken()) {
            if (!$user->tokenCan('admin') && !$user->tokenCan('*')) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Token de acesso sem privilégios administrativos suficientes.',
                    'code'    => 'TOKEN_SCOPE',
                ], 403);
            }
        }

        return $next($request);
    }
}
