<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();
            $table->decimal('valor_total', 10, 2);
            $table->enum('status_pagamento', ['pendente', 'aprovado', 'falhou', 'cancelado'])->default('pendente');
            $table->enum('status_entrega', ['pendente', 'enviado', 'entregue', 'cancelado'])->default('pendente');
            $table->dateTime('data_do_pedido')->useCurrent(); 
            $table->foreignId('usuario_id', 45);
            $table->foreignId('usuario_id')->constrained('usuarios');
            $table->foreignId('endereco_id')->constrained('enderecos'); 
            $table->timestamps();
        });
    }

    public function down(): void
    {   
        //ATENÇÃO! NUNCA RODE comandos de rollback ou refresh em ambiente de produção 
        Schema::dropIfExists('pedidos');
    }
};