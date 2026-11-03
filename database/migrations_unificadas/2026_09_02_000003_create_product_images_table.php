<?php

/**
 * IMAGENS DE PRODUTO (Cloudinary).
 *
 * Origem: cliente.
 * - cloudinary_public_id permite apagar o arquivo no Cloudinary quando a imagem sai.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('image_url', 255);
            $table->string('cloudinary_public_id', 150)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
