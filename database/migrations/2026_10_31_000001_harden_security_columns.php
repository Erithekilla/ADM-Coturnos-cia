<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Prepara o banco para as diretrizes de .MCP/SEGURANCA_E_AUTENTICACAO.md.
 *
 * Idempotente: pode ser executada com segurança em um banco que já tenha parte
 * das colunas. Rode-a isoladamente com:
 *   php artisan migrate --path=database/migrations/2026_10_31_000001_harden_security_columns.php
 *
 * Depois, cifre os dados legados com:
 *   php artisan seguranca:criptografar-dados
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->hardenUsuario();
        $this->ensurePersonalAccessTokens();
        $this->hardenAuditLogs();
    }

    /**
     * 4.1/4.2 — Colunas cifradas (AES-256 ocupa ~200-300 chars) + Blind Index.
     */
    private function hardenUsuario(): void
    {
        $targetTable = Schema::hasTable('users') ? 'users' : (Schema::hasTable('usuario') ? 'usuario' : null);
        if (!$targetTable) {
            return;
        }

        // CPF e nascimento passam a guardar o payload cifrado (string longa).
        Schema::table($targetTable, function (Blueprint $table) use ($targetTable) {
            if (Schema::hasColumn($targetTable, 'cpf')) {
                $table->string('cpf', 512)->nullable()->change();
            }

            $birthCol = Schema::hasColumn($targetTable, 'birth_date') ? 'birth_date' : (Schema::hasColumn($targetTable, 'data_nascimento') ? 'data_nascimento' : null);
            if ($birthCol) {
                $table->string($birthCol, 512)->nullable()->change();
            }
        });

        Schema::table($targetTable, function (Blueprint $table) use ($targetTable) {
            $birthCol = Schema::hasColumn($targetTable, 'birth_date') ? 'birth_date' : 'data_nascimento';
            if (!Schema::hasColumn($targetTable, $birthCol)) {
                $table->string($birthCol, 512)->nullable();
            }

            $phoneCol = Schema::hasColumn($targetTable, 'phone') ? 'phone' : 'telefone';
            if (!Schema::hasColumn($targetTable, $phoneCol)) {
                $table->string($phoneCol, 512)->nullable();
            }

            if (!Schema::hasColumn($targetTable, 'cpf_hash')) {
                $table->char('cpf_hash', 64)->nullable()->unique();
            }
            if (!Schema::hasColumn($targetTable, 'status')) {
                $table->string('status', 20)->nullable()->default('ativo');
            }

            $delCol = Schema::hasColumn($targetTable, 'is_deleted') ? 'is_deleted' : 'excluido';
            if (!Schema::hasColumn($targetTable, $delCol)) {
                $table->char($delCol, 1)->nullable()->default('N');
            }
        });
    }

    /**
     * Tabela de tokens do Sanctum (só cria se ainda não existir).
     */
    private function ensurePersonalAccessTokens(): void
    {
        if (Schema::hasTable('personal_access_tokens')) {
            return;
        }

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

    /**
     * 2.4 — Campos auditados do padrão documentado, sem remover as colunas
     * legadas (autor, acao, tabela, registro_id, ip, detalhes) usadas pelo painel.
     */
    private function hardenAuditLogs(): void
    {
        if (!Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('admin_user_id')->nullable()->index();
                $table->string('action', 100);
                $table->string('table_affected', 60);
                $table->unsignedBigInteger('record_id')->default(0);
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('autor')->nullable();
                $table->string('acao', 100)->nullable();
                $table->string('tabela', 60)->nullable();
                $table->unsignedBigInteger('registro_id')->nullable();
                $table->string('ip', 45)->nullable();
                $table->text('detalhes')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });

            return;
        }

        // A FK antiga apontava para `users`, mas os administradores vivem em `usuario`.
        foreach (Schema::getForeignKeys('audit_logs') as $fk) {
            if (in_array('admin_user_id', $fk['columns'], true)) {
                $alvo = DB::getDriverName() === 'sqlite' ? $fk['columns'] : $fk['name'];
                Schema::table('audit_logs', fn (Blueprint $table) => $table->dropForeign($alvo));
            }
        }

        $columns = [
            'admin_user_id'  => fn (Blueprint $t) => $t->unsignedBigInteger('admin_user_id')->nullable()->index(),
            'action'         => fn (Blueprint $t) => $t->string('action', 100)->nullable(),
            'table_affected' => fn (Blueprint $t) => $t->string('table_affected', 60)->nullable(),
            'record_id'      => fn (Blueprint $t) => $t->unsignedBigInteger('record_id')->nullable(),
            'old_values'     => fn (Blueprint $t) => $t->json('old_values')->nullable(),
            'new_values'     => fn (Blueprint $t) => $t->json('new_values')->nullable(),
            'ip_address'     => fn (Blueprint $t) => $t->string('ip_address', 45)->nullable(),
            'autor'          => fn (Blueprint $t) => $t->string('autor')->nullable(),
            'acao'           => fn (Blueprint $t) => $t->string('acao', 100)->nullable(),
            'tabela'         => fn (Blueprint $t) => $t->string('tabela', 60)->nullable(),
            'registro_id'    => fn (Blueprint $t) => $t->unsignedBigInteger('registro_id')->nullable(),
            'ip'             => fn (Blueprint $t) => $t->string('ip', 45)->nullable(),
            'detalhes'       => fn (Blueprint $t) => $t->text('detalhes')->nullable(),
            'created_at'     => fn (Blueprint $t) => $t->timestamp('created_at')->nullable()->useCurrent(),
        ];

        foreach ($columns as $name => $definition) {
            if (!Schema::hasColumn('audit_logs', $name)) {
                Schema::table('audit_logs', fn (Blueprint $table) => $definition($table));
            }
        }
    }

    public function down(): void
    {
        // Intencionalmente sem rollback destrutivo: reverter o tamanho de `cpf`
        // truncaria os CPFs cifrados e os tornaria irrecuperáveis.
    }
};
