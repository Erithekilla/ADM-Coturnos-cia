<?php

namespace App\Providers;

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
    }
}

