<?php

/**
 * TRILHA DE AUDITORIA (diretriz 2.4) — append-only.
 *
 * Origem: cliente (campos do padrão documentado) + admin (rótulo do autor e texto
 * descritivo que o painel exibe).
 *
 * As colunas legadas duplicadas (acao, tabela, registro_id, ip) foram eliminadas;
 * elas repetiam action, table_affected, record_id e ip_address. As duas que tinham
 * conteúdo próprio foram mantidas com nome em inglês:
 *   autor    → actor_label   (ex.: "Maria Souza (m***@email.com)" ou "Sistema")
 *   detalhes → description
 *
 * - admin_user_id → users com SET NULL: o log sobrevive à exclusão do autor.
 *   (Agora que admins vivem em users, a FK volta a existir.)
 * - Sem updated_at: registros nunca são alterados. O model AuditLog bloqueia
 *   UPDATE/DELETE; no MySQL também é recomendável um usuário de banco da aplicação
 *   sem privilégio de UPDATE/DELETE nesta tabela.
 * - old_values/new_values chegam já sanitizados pelo AuditLogger (sem senha/CPF).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_label', 191)->nullable();
            $table->string('action', 100);
            $table->string('table_affected', 60);
            $table->unsignedBigInteger('record_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('description')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['table_affected', 'record_id']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
