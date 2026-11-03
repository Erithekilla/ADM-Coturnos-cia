<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductPriceController;
use App\Http\Controllers\ProductPromotionController;
use App\Http\Controllers\ProductSizeController;
use App\Http\Controllers\ProductImageController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\CollectionProductController;
use App\Http\Controllers\CollectionImageController;
use App\Http\Controllers\BannerController;
use App\Http\Controllers\BannerImageController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CartItemController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderItemController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\OrderHistoryController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminProductController;
use App\Http\Controllers\AdminOrderController;
use App\Http\Controllers\AdminUserController;

/*
|--------------------------------------------------------------------------
| API Routes — see .MCP/SEGURANCA_E_AUTENTICACAO.md
|--------------------------------------------------------------------------
| Public ............. auth/login, auth/register (rate limited)
| Authenticated ...... auth:sanctum (profile, logout, user's own cart)
| Admin .............. auth:sanctum + admin + throttle:admin-api
*/

// Legacy upload banner route restricted to administrators
Route::post('upload-store-images', [BannerController::class, 'uploadStoreImages'])
    ->middleware(['auth:sanctum', 'admin', 'throttle:admin-api']);

Route::prefix('v1')->group(function () {

    // ---------------------------------------------------------------
    // Public routes
    // ---------------------------------------------------------------
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:register');

    // ---------------------------------------------------------------
    // Authenticated user (scoped to token owner)
    // ---------------------------------------------------------------
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        // Cart (dual language support)
        Route::get('carrinho', [CartController::class, 'getMeCarrinho']);
        Route::get('cart', [CartController::class, 'getMeCarrinho']);
        Route::post('carrinho/itens', [CartController::class, 'adicionarItem']);
        Route::post('cart/items', [CartController::class, 'adicionarItem']);
        Route::put('carrinho/itens', [CartController::class, 'atualizarItem']);
        Route::put('cart/items', [CartController::class, 'atualizarItem']);
        Route::delete('carrinho/itens', [CartController::class, 'removerItem']);
        Route::delete('cart/items', [CartController::class, 'removerItem']);
        Route::delete('carrinho/limpar', [CartController::class, 'limparCarrinho']);
        Route::delete('cart/clear', [CartController::class, 'limparCarrinho']);
        Route::post('carrinho/sync', [CartController::class, 'sincronizarCarrinho']);
        Route::post('cart/sync', [CartController::class, 'sincronizarCarrinho']);
    });

    // ---------------------------------------------------------------
    // Administrators only
    // ---------------------------------------------------------------
    Route::middleware(['auth:sanctum', 'admin', 'throttle:admin-api'])->group(function () {

        Route::prefix('admin')->group(function () {
            Route::get('dashboard', [AdminDashboardController::class, 'dashboard']);
            Route::get('logs', [AdminDashboardController::class, 'logs']);

            Route::post('upload', [AdminProductController::class, 'upload']);

            Route::post('produtos', [AdminProductController::class, 'store']);
            Route::post('products', [AdminProductController::class, 'store']);
            Route::put('produtos/{id}', [AdminProductController::class, 'update']);
            Route::put('products/{id}', [AdminProductController::class, 'update']);
            Route::delete('produtos/{id}', [AdminProductController::class, 'destroy']);
            Route::delete('products/{id}', [AdminProductController::class, 'destroy']);

            Route::patch('pedidos/{id}/status', [AdminOrderController::class, 'updateStatus']);
            Route::patch('orders/{id}/status', [AdminOrderController::class, 'updateStatus']);

            Route::get('usuarios', [AdminUserController::class, 'index']);
            Route::get('users', [AdminUserController::class, 'index']);
            Route::get('usuarios/buscar-cpf', [AdminUserController::class, 'searchByCpf']);
            Route::get('users/search-cpf', [AdminUserController::class, 'searchByCpf']);
            Route::get('usuarios/{id}', [AdminUserController::class, 'show']);
            Route::get('users/{id}', [AdminUserController::class, 'show']);
            Route::patch('usuarios/{id}/toggle-status', [AdminUserController::class, 'toggleStatus']);
            Route::patch('users/{id}/toggle-status', [AdminUserController::class, 'toggleStatus']);
            Route::patch('usuarios/{id}/toggle-role', [AdminUserController::class, 'toggleRole']);
            Route::patch('users/{id}/toggle-role', [AdminUserController::class, 'toggleRole']);
        });

        Route::post('upload-store-images', [BannerController::class, 'uploadStoreImages']);

        // Resources with dual language routes (Portuguese legacy + English clean)
        Route::apiResource('usuarios', UserController::class);
        Route::apiResource('users', UserController::class);

        Route::apiResource('produtos', ProductController::class);
        Route::apiResource('products', ProductController::class);

        Route::apiResource('precos', ProductPriceController::class);
        Route::apiResource('product-prices', ProductPriceController::class);

        Route::apiResource('promocoes-produtos', ProductPromotionController::class);
        Route::apiResource('product-promotions', ProductPromotionController::class);

        Route::apiResource('tamanhos-produtos', ProductSizeController::class);
        Route::apiResource('product-sizes', ProductSizeController::class);

        Route::apiResource('imagens-produtos', ProductImageController::class);
        Route::apiResource('product-images', ProductImageController::class);

        Route::apiResource('colecoes', CollectionController::class);
        Route::apiResource('collections', CollectionController::class);

        Route::apiResource('colecoes-produtos', CollectionProductController::class);
        Route::apiResource('collection-products', CollectionProductController::class);

        Route::apiResource('imagens-colecoes', CollectionImageController::class);
        Route::apiResource('collection-images', CollectionImageController::class);

        Route::apiResource('banners', BannerController::class);

        Route::apiResource('imagens-banners', BannerImageController::class);
        Route::apiResource('banner-images', BannerImageController::class);

        Route::apiResource('enderecos', AddressController::class);
        Route::apiResource('addresses', AddressController::class);

        Route::apiResource('carrinhos', CartController::class);
        Route::apiResource('carts', CartController::class);

        Route::apiResource('itens-carrinhos', CartItemController::class);
        Route::apiResource('cart-items', CartItemController::class);

        Route::apiResource('pedidos', OrderController::class);
        Route::apiResource('orders', OrderController::class);

        Route::apiResource('itens-pedidos', OrderItemController::class);
        Route::apiResource('order-items', OrderItemController::class);

        Route::apiResource('pagamentos', PaymentController::class);
        Route::apiResource('payments', PaymentController::class);

        Route::apiResource('historicos-pedidos', OrderHistoryController::class);
        Route::apiResource('order-history', OrderHistoryController::class);
    });
});
