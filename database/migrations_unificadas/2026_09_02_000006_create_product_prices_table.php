<?php

/**
 * HISTÓRICO DE PREÇOS (admin).
 *
 * Origem: admin (`precos`). Cada alteração de preço vira uma linha nova; o preço
 * vigente é copiado para products.base_price. Linhas antigas não devem ser editadas.
 * - created_by: qual administrador registrou o preço (rastreabilidade além do audit_log).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('price', 10, 2);
            $table->dateTime('effective_date');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_prices');
    }
};
