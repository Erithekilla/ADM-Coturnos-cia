<?php

/**
 * EMBALAGENS PADRÃO PARA FRETE (admin).
 *
 * Origem: admin (`packed_products`, usado no cálculo do SuperFrete).
 * Guarda medidas de caixa usadas quando o produto não tem dimensões próprias.
 * Os padrões são os mesmos do model PackedProduct (1,5 kg, 14 x 22 x 35 cm).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packed_products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->nullable();
            $table->decimal('weight', 8, 2)->default(1.50); // kg
            $table->decimal('height', 8, 2)->default(14.00); // cm
            $table->decimal('width', 8, 2)->default(22.00);  // cm
            $table->decimal('length', 8, 2)->default(35.00); // cm
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packed_products');
    }
};
