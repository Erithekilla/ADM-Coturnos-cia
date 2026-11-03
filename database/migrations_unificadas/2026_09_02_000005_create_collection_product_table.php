<?php

/**
 * PIVÔ COLEÇÃO x PRODUTO.
 *
 * Origem: cliente. Nome no padrão do Laravel (`collection_product`, singular em ordem
 * alfabética). O admin usava `collection_products` → ajustar os models do admin.
 * - Chave primária composta impede o mesmo produto duas vezes na mesma coleção.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_product', function (Blueprint $table) {
            $table->foreignId('collection_id')->constrained('collections')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['collection_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_product');
    }
};
