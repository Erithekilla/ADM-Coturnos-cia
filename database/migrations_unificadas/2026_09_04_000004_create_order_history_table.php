<?php

/**
 * HISTÓRICO DE STATUS DO PEDIDO (admin).
 *
 * Origem: admin (`historico_pedido`).
 * - status usa o mesmo conjunto de valores de orders.status.
 * - changed_by: administrador (ou nulo, quando foi o sistema/webhook) que mudou o status.
 * - Somente inserção: cada mudança vira uma linha nova.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->enum('status', ['pending', 'paid', 'preparing', 'shipped', 'delivered', 'cancelled']);
            $table->dateTime('status_date');
            $table->string('notes', 255)->nullable();
            $table->string('extra_info', 255)->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['order_id', 'status_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_history');
    }
};
