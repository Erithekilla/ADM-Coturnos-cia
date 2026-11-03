<?php

/**
 * CONTAS SOCIAIS (OAuth: Google, Facebook, Apple).
 *
 * Origem: cliente. Substitui as colunas soltas users.google_id / users.provider do admin,
 * permitindo vários provedores por usuário.
 * - UNIQUE(provider_name, provider_id): impede que a mesma conta social seja
 *   vinculada a dois usuários (sequestro de conta por vínculo duplicado).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider_name', 30); // 'google', 'facebook', 'apple'
            $table->string('provider_id', 191);
            $table->timestamps();

            $table->unique(['provider_name', 'provider_id']);
            $table->unique(['user_id', 'provider_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
