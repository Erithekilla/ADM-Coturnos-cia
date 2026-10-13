<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || strtolower((string)$user->role) !== 'admin') {
            return response()->json([
                'status' => 'error',
                'message' => 'Acesso proibido. Apenas administradores possuem autorização para executar esta ação.'
            ], 403);
        }

        return $next($request);
    }
}

