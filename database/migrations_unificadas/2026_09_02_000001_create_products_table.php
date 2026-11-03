<?php

/**
 * PRODUTOS.
 *
 * Origem: cliente (slug, preços, dimensões de frete, softDeletes)
 *       + admin (model, color).
 *
 * Preço: base_price/promotional_price guardam o preço VIGENTE (o front do cliente
 * lê direto daqui). O histórico fica em product_prices / product_promotions, que o
 * admin alimenta; ao salvar um preço/promoção, o admin atualiza estas colunas.
 *
 * softDeletes no lugar de is_deleted 'S'/'N': um produto vendido nunca pode ser
 * apagado fisicamente (order_items aponta para ele com RESTRICT).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->string('product_type', 45);            // Coturno Tático, Coturno Militar...
            $table->string('brand', 45);
            $table->string('model', 45)->nullable();
            $table->string('color', 45)->nullable();
            $table->text('description');
            $table->decimal('base_price', 10, 2);
            $table->decimal('promotional_price', 10, 2)->nullable();
            $table->decimal('weight', 8, 2)->nullable();    // kg  (frete)
            $table->decimal('height', 8, 2)->nullable();    // cm
            $table->decimal('width', 8, 2)->nullable();     // cm
            $table->decimal('length', 8, 2)->nullable();    // cm
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
