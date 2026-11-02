<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        \App\Models\Produto::observe(\App\Observers\ProdutoObserver::class);
        \App\Models\Pedido::observe(\App\Observers\PedidoObserver::class);

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        // 6.1 — Limite isolado e restritivo para endpoints administrativos.
        RateLimiter::for('admin-api', function (Request $request) {
            $key = $request->user()?->id ? 'admin_' . $request->user()->id : $request->ip();

            return Limit::perMinute((int) env('ADMIN_API_RATE_LIMIT', 60))->by($key)->response(function () {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Muitas requisições administrativas. Limite de segurança atingido. Aguarde antes de tentar novamente.'
                ], 429);
            });
        });

        // Força bruta no login: 5 tentativas/minuto por e-mail+IP e 20/minuto por IP.
        RateLimiter::for('login', function (Request $request) {
            $email = strtolower((string) $request->input('email'));

            $response = fn () => response()->json([
                'status'  => 'error',
                'message' => 'Muitas tentativas de login. Aguarde um minuto e tente novamente.'
            ], 429);

            return [
                Limit::perMinute(5)->by('login:' . $email . '|' . $request->ip())->response($response),
                Limit::perMinute(20)->by('login-ip:' . $request->ip())->response($response),
            ];
        });

        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(3)->by('register:' . $request->ip())->response(fn () => response()->json([
                'status'  => 'error',
                'message' => 'Muitas tentativas de cadastro. Aguarde antes de tentar novamente.'
            ], 429));
        });
    }
}
