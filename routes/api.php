<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\ProdutoController;
use App\Http\Controllers\PrecoController;
use App\Http\Controllers\PromocaoProdutoController;
use App\Http\Controllers\TamanhoProdutoController;
use App\Http\Controllers\ImagensProdutoController;
use App\Http\Controllers\ColecaoController;
use App\Http\Controllers\ColecaoHasProdutoController;
use App\Http\Controllers\ImagensColecaoController;
use App\Http\Controllers\BannerController;
use App\Http\Controllers\ImagensBannerController;
use App\Http\Controllers\EnderecoController;
use App\Http\Controllers\CarrinhoController;
use App\Http\Controllers\ItensCarrinhoController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\ItensPedidoController;
use App\Http\Controllers\PagamentoController;
use App\Http\Controllers\HistoricoPedidoController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminProdutoController;
use App\Http\Controllers\AdminPedidoController;
use App\Http\Controllers\AdminUsuarioController;

/*
|--------------------------------------------------------------------------
| Rotas da API — ver .MCP/SEGURANCA_E_AUTENTICACAO.md
|--------------------------------------------------------------------------
| Públicas ............ apenas login e cadastro (com rate limit)
| Autenticadas ........ auth:sanctum (perfil, logout, carrinho do próprio usuário)
| Administrativas ..... auth:sanctum + admin (4 barreiras) + throttle:admin-api
*/

// Legado: mantida para compatibilidade, agora restrita a administradores.
Route::post('upload-store-images', [BannerController::class, 'uploadStoreImages'])
    ->middleware(['auth:sanctum', 'admin', 'throttle:admin-api']);

Route::prefix('v1')->group(function () {

    // ---------------------------------------------------------------
    // Públicas
    // ---------------------------------------------------------------
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:register');

    // ---------------------------------------------------------------
    // Qualquer usuário autenticado (escopo sempre pelo dono do token)
    // ---------------------------------------------------------------
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::get('carrinho', [CarrinhoController::class, 'getMeCarrinho']);
        Route::post('carrinho/itens', [CarrinhoController::class, 'adicionarItem']);
        Route::put('carrinho/itens', [CarrinhoController::class, 'atualizarItem']);
        Route::delete('carrinho/itens', [CarrinhoController::class, 'removerItem']);
        Route::delete('carrinho/limpar', [CarrinhoController::class, 'limparCarrinho']);
        Route::post('carrinho/sync', [CarrinhoController::class, 'sincronizarCarrinho']);
    });

    // ---------------------------------------------------------------
    // Somente administradores
    // ---------------------------------------------------------------
    Route::middleware(['auth:sanctum', 'admin', 'throttle:admin-api'])->group(function () {

        Route::prefix('admin')->group(function () {
            Route::get('dashboard', [AdminDashboardController::class, 'dashboard']);
            Route::get('logs', [AdminDashboardController::class, 'logs']);

            Route::post('upload', [AdminProdutoController::class, 'upload']);

            Route::post('produtos', [AdminProdutoController::class, 'store']);
            Route::put('produtos/{id}', [AdminProdutoController::class, 'update']);
            Route::delete('produtos/{id}', [AdminProdutoController::class, 'destroy']);

            Route::patch('pedidos/{id}/status', [AdminPedidoController::class, 'updateStatus']);

            Route::get('usuarios', [AdminUsuarioController::class, 'index']);
            Route::get('usuarios/buscar-cpf', [AdminUsuarioController::class, 'searchByCpf']);
            Route::get('usuarios/{id}', [AdminUsuarioController::class, 'show']);
            Route::patch('usuarios/{id}/toggle-status', [AdminUsuarioController::class, 'toggleStatus']);
            Route::patch('usuarios/{id}/toggle-role', [AdminUsuarioController::class, 'toggleRole']);
        });

        Route::post('upload-store-images', [BannerController::class, 'uploadStoreImages']);

        Route::apiResource('usuarios', UsuarioController::class);
        Route::apiResource('produtos', ProdutoController::class);
        Route::apiResource('precos', PrecoController::class);
        Route::apiResource('promocoes-produtos', PromocaoProdutoController::class);
        Route::apiResource('tamanhos-produtos', TamanhoProdutoController::class);
        Route::apiResource('imagens-produtos', ImagensProdutoController::class);
        Route::apiResource('colecoes', ColecaoController::class);
        Route::apiResource('colecoes-produtos', ColecaoHasProdutoController::class);
        Route::apiResource('imagens-colecoes', ImagensColecaoController::class);
        Route::apiResource('banners', BannerController::class);
        Route::apiResource('imagens-banners', ImagensBannerController::class);
        Route::apiResource('enderecos', EnderecoController::class);
        Route::apiResource('carrinhos', CarrinhoController::class);
        Route::apiResource('itens-carrinhos', ItensCarrinhoController::class);
        Route::apiResource('pedidos', PedidoController::class);
        Route::apiResource('itens-pedidos', ItensPedidoController::class);
        Route::apiResource('pagamentos', PagamentoController::class);
        Route::apiResource('historicos-pedidos', HistoricoPedidoController::class);
    });
});
