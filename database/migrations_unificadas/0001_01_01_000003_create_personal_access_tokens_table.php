<?php

/**
 * TOKENS DO SANCTUM (cliente e admin).
 *
 * Uma única migration (o admin tinha duas duplicadas).
 * - token: o Sanctum grava apenas o SHA-256 do token; o texto puro é mostrado uma vez.
 * - expires_at indexado para a limpeza periódica (php artisan sanctum:prune-expired).
 * - abilities: o admin recebe ['admin'] e o EnsureUserIsAdmin confere (barreira 4).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
