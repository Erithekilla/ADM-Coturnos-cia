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

// Rota de upload legado/compatibilidade fora de v1
Route::post('upload-store-images', [BannerController::class, 'uploadStoreImages']);

Route::prefix('v1')->group(function () {
    // -------------------------------------------------------------------------
    // Módulo Administrativo (Plano Operacional Backend Admin)
    // -------------------------------------------------------------------------
    Route::prefix('admin')->group(function () {
        // Semana 4: Dashboard & Logs (com Cache e métricas inteligentes)
        Route::get('dashboard', [AdminDashboardController::class, 'dashboard']);
        Route::get('logs', [AdminDashboardController::class, 'logs']);

        // Semana 2: Upload isolado para testes com Cloudinary
        Route::post('upload', [AdminProdutoController::class, 'upload']);

        // Semana 2: CRUD de Produtos do Admin
        Route::post('produtos', [AdminProdutoController::class, 'store']);
        Route::put('produtos/{id}', [AdminProdutoController::class, 'update']);
        Route::delete('produtos/{id}', [AdminProdutoController::class, 'destroy']);

        // Semana 3: Máquina de status de pedidos + Estorno automático de estoque
        Route::patch('pedidos/{id}/status', [AdminPedidoController::class, 'updateStatus']);

        // Semana 3: Gestão de status e roles de usuários
        Route::get('usuarios', [AdminUsuarioController::class, 'index']);
        Route::patch('usuarios/{id}/toggle-status', [AdminUsuarioController::class, 'toggleStatus']);
        Route::patch('usuarios/{id}/toggle-role', [AdminUsuarioController::class, 'toggleRole']);
    });

    // Upload de imagens no padrão v1
    Route::post('upload-store-images', [BannerController::class, 'uploadStoreImages']);

    // Autenticação
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);

    // Carrinho do Usuário
    Route::get('carrinho', [CarrinhoController::class, 'getMeCarrinho']);
    Route::post('carrinho/itens', [CarrinhoController::class, 'adicionarItem']);
    Route::put('carrinho/itens', [CarrinhoController::class, 'atualizarItem']);
    Route::delete('carrinho/itens', [CarrinhoController::class, 'removerItem']);
    Route::delete('carrinho/limpar', [CarrinhoController::class, 'limparCarrinho']);
    Route::post('carrinho/sync', [CarrinhoController::class, 'sincronizarCarrinho']);

    // Recursos da API REST (Compatibilidade total com Front-end existente)
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
