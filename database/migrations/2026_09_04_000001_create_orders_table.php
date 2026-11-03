<?php

/**
 * PEDIDOS.
 *
 * Origem: cliente (order_number, subtotal/frete/desconto, shipping_method)
 *       + admin (tracking_code 100).
 *
 * Correções de segurança/integridade:
 * - user_id com RESTRICT: apagar um usuário NÃO apaga o histórico de vendas
 *   (exigência fiscal). Usuários usam softDeletes.
 * - Cópia do endereço de entrega (shipping_*): o pedido continua sabendo para onde
 *   foi enviado mesmo que o cliente edite ou apague o endereço.
 * - status com UM conjunto de valores (inglês). O enum bilíngue permitia 'paid' e
 *   'pago' ao mesmo tempo, o que quebra filtros e a máquina de estados.
 * - order_number é o identificador público. Gere-o aleatório (ex.: 'CC-' + 10
 *   caracteres), nunca sequencial, para não vazar volume de vendas (diretriz 3).
 * - Valores monetários são calculados no backend a partir de products.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 20)->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('address_id')->nullable()->constrained('addresses')->nullOnDelete();

            // Endereço de entrega congelado no momento da compra
            $table->string('shipping_zip_code', 9)->nullable();
            $table->string('shipping_street', 120)->nullable();
            $table->string('shipping_number', 20)->nullable();
            $table->string('shipping_complement', 100)->nullable();
            $table->string('shipping_neighborhood', 60)->nullable();
            $table->string('shipping_city', 60)->nullable();
            $table->char('shipping_state', 2)->nullable();

            $table->enum('status', ['pending', 'paid', 'preparing', 'shipped', 'delivered', 'cancelled'])
                ->default('pending')
                ->index();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('shipping_fee', 10, 2)->default(0);
            $table->decimal('discount_fee', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2);
            $table->string('shipping_method', 50)->nullable(); // PAC, SEDEX
            $table->string('tracking_code', 100)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
