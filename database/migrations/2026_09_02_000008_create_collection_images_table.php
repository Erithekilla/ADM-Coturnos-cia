<?php

/**
 * IMAGENS DE COLEÇÃO (admin).
 *
 * Origem: admin (`imagens_colecao`). image_url passou de 45 para 255 caracteres:
 * uma URL do Cloudinary não cabe em 45 e seria truncada.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained('collections')->cascadeOnDelete();
            $table->string('image_url', 255);
            $table->string('cloudinary_public_id', 150)->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_images');
    }
};
