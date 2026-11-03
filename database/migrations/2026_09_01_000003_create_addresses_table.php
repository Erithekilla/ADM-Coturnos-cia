<?php

/**
 * ENDEREÇOS DO CLIENTE.
 *
 * Origem: cliente (com softDeletes).
 * - softDeletes: o cliente "apaga" o endereço, mas o registro continua existindo.
 * - O pedido NÃO depende deste registro para saber para onde foi entregue:
 *   orders guarda uma cópia do endereço (shipping_*) no momento da compra.
 * - Toda consulta pelo lado do cliente deve ser escopada:
 *   $request->user()->addresses()->findOrFail($id)  (anti-IDOR, diretriz 3.3).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('zip_code', 9);
            $table->string('street', 120);
            $table->string('number', 20);
            $table->string('complement', 100)->nullable();
            $table->string('neighborhood', 60);
            $table->string('city', 60);
            $table->char('state', 2); // UF
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
