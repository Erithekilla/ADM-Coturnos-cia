<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produtos', function (Blueprint $table) {
            $table->id();
            $table->string('tipo_do_produto', 45);
            $table->string('modelo', 45);
            $table->string('marca', 45);
            //fazer alterações do atributo descrição para text() no banco e aqui
            $table->string('descricao', 45);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {   
        //ATENÇÃO! NUNCA RODE comandos de rollback ou refresh em ambiente de produção 
        Schema::dropIfExists('produtos');
    }
};