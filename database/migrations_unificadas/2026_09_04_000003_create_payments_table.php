<?php

/**
 * PAGAMENTOS (Mercado Pago – Checkout Pro / Pix).
 *
 * Origem: cliente (preference_id, gateway_payment_id, init_point, Pix)
 *       + admin (data do pagamento → paid_at).
 *
 * Segurança (diretriz 4.3 / PCI-DSS):
 * - Nunca guardar número de cartão, CVV ou senha. Só últimos 4 dígitos e bandeira.
 * - gateway_payment_id UNIQUE: o mesmo webhook reenviado não é processado duas vezes
 *   (idempotência). Valide também a assinatura x-signature do Mercado Pago.
 * - response_payload é LONGTEXT (não JSON) porque deve ser gravado CIFRADO com o
 *   cast 'encrypted:array': o payload do gateway traz e-mail, CPF e nome do pagador.
 *   Uma coluna JSON rejeitaria o texto cifrado.
 * - order_id com RESTRICT: registro financeiro não some junto com o pedido.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->string('gateway', 30)->default('mercadopago');
            $table->string('preference_id', 100)->nullable()->index();   // preferência do Checkout Pro
            $table->string('gateway_payment_id', 100)->nullable()->unique(); // ID da transação (webhook)
            $table->string('method', 50)->nullable();                     // credit_card, pix, ticket
            $table->enum('status', ['pending', 'approved', 'in_process', 'rejected', 'cancelled', 'refunded'])
                ->default('pending')
                ->index();
            $table->decimal('amount', 10, 2);
            $table->unsignedTinyInteger('installments')->default(1);
            $table->char('card_last_four', 4)->nullable();
            $table->string('card_brand', 30)->nullable();
            $table->text('init_point')->nullable();       // URL de redirecionamento do gateway
            $table->text('qr_code_pix')->nullable();
            $table->longText('qr_code_base64')->nullable();
            $table->longText('response_payload')->nullable(); // cifrado: cast 'encrypted:array'
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
