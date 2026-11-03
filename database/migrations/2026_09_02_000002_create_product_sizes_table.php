<?php

/**
 * TAMANHOS E ESTOQUE.
 *
 * Origem: cliente. (O admin chamava a coluna de estoque de `quantity`.)
 * - UNIQUE(product_id, size): um único registro de estoque por tamanho.
 * - Baixa de estoque deve ser atômica no checkout:
 *   UPDATE ... SET stock = stock - ? WHERE id = ? AND stock >= ?  (evita estoque negativo).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedSmallInteger('size');   // 37 a 44
            $table->unsignedInteger('stock')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'size']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_sizes');
    }
};
