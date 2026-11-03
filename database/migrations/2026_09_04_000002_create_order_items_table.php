<?php

/**
 * ITENS DO PEDIDO.
 *
 * Origem: cliente.
 * Correções:
 * - product_id com RESTRICT: não é possível apagar fisicamente um produto vendido
 *   (produtos usam softDeletes). O admin legado tinha CASCADE aqui, o que apagaria
 *   as vendas junto com o produto.
 * - product_name: cópia do nome no momento da compra; renomear o produto não
 *   altera o histórico do pedido.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('product_name', 120)->nullable();
            $table->unsignedSmallInteger('size');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('subtotal', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
