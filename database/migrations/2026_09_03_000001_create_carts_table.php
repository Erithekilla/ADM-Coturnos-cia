<?php

/**
 * CARRINHOS (usuário logado ou visitante).
 *
 * Origem: cliente.
 * Segurança:
 * - session_id (visitante) deve ser gerado pelo SERVIDOR com Str::random(40)+ e
 *   nunca aceito como veio do cliente; tamanho 100 comporta isso.
 * - session_id é UNIQUE: um identificador de visitante aponta para um único carrinho.
 * - Rotas de carrinho de usuário logado continuam escopadas pelo dono do token
 *   ($request->user()->carts()), sem aceitar user_id vindo da requisição (3.3).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('session_id', 100)->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
