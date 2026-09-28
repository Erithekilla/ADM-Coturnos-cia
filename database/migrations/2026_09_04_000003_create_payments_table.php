<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->string('gateway', 30)->default('mercadopago');
            $table->string('preference_id', 100)->nullable()->index(); // ID da Preferência criada no MP (Checkout Pro)
            $table->string('gateway_payment_id', 100)->nullable()->index(); // ID da transação após aprovação via Webhook
            $table->string('method', 50)->nullable(); // 'credit_card', 'pix', 'ticket' retornado via Webhook
            $table->enum('status', ['pending', 'approved', 'in_process', 'rejected', 'cancelled'])->default('pending');
            $table->decimal('amount', 10, 2);
            $table->text('init_point')->nullable(); // URL de redirecionamento gerada pelo gateway (Checkout Pro)
            $table->integer('installments')->default(1);
            $table->text('qr_code_pix')->nullable();
            $table->longText('qr_code_base64')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
