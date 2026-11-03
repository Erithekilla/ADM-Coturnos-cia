<?php

/**
 * ITENS DO CARRINHO.
 *
 * Origem: cliente.
 * Segurança:
 * - unit_price é só uma referência de exibição. No checkout o preço SEMPRE é
 *   recalculado a partir de products (nunca confiar no valor do carrinho/front).
 * - UNIQUE(cart_id, product_id, size): o mesmo tamanho soma quantidade em vez de
 *   criar linhas duplicadas.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained('carts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedSmallInteger('size');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->timestamps();

            $table->unique(['cart_id', 'product_id', 'size']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
