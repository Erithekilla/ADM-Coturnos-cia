<?php

/**
 * PROMOÇÕES (admin).
 *
 * Origem: admin (`promocao_produtos`). Mantém o vínculo com o preço (price_id) como
 * no painel atual. A promoção vigente é copiada para products.promotional_price.
 * - active é boolean (o legado usava 'ativo'/'inativo' em texto).
 * - O backend deve validar promotional_price < price e end_date >= start_date.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_id')->constrained('product_prices')->cascadeOnDelete();
            $table->decimal('promotional_price', 10, 2);
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['active', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_promotions');
    }
};
