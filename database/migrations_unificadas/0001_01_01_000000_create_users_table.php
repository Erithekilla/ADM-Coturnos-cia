<?php

/**
 * USUÁRIOS (clientes e administradores na MESMA tabela) + reset de senha + sessões.
 *
 * Origem:
 * - Estrutura e nomes: migrations do cliente.
 * - Tamanho das colunas cifradas, cpf_hash CHAR(64): admin (diretriz 4.1/4.2).
 *
 * Segurança:
 * - cpf, phone e birth_date guardam o payload AES-256-CBC (cast SafeEncrypted).
 *   Um valor curto cifrado ocupa ~204 caracteres e cresce com o tamanho do dado,
 *   por isso 512 (255 truncaria valores >= 32 bytes e os tornaria irrecuperáveis).
 *   birth_date é STRING de propósito: uma coluna DATE não aceita o texto cifrado.
 * - cpf_hash: Blind Index HMAC-SHA256 (UNIQUE já cria o índice; não duplicar).
 * - role: ENUM fecha no banco os valores aceitos (defesa extra além do $fillable).
 * - active + softDeletes substituem status 'ativo'/'inativo' e is_deleted 'S'/'N'.
 *   Usuário nunca é apagado fisicamente: pedidos e auditoria dependem dele.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 60);
            $table->string('last_name', 60);
            $table->string('cpf', 512)->nullable();          // cifrado (AES-256-CBC); nulo no login social
            $table->char('cpf_hash', 64)->nullable()->unique(); // Blind Index HMAC-SHA256
            $table->string('email', 191)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('phone', 512)->nullable();        // cifrado
            $table->string('birth_date', 512)->nullable();   // cifrado (por isso não é DATE)
            $table->string('password', 255)->nullable();     // hash bcrypt/argon2id; nulo no login social puro
            $table->enum('role', ['customer', 'admin'])->default('customer');
            $table->boolean('active')->default(true)->index();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email', 191)->primary();
            $table->string('token');                          // o Laravel grava apenas o hash
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
