<?php

/**
 * BANNERS DA HOME (admin).
 *
 * Origem: admin (`banner` / `imagens_banner`).
 * - active boolean (legado: 'ativo'/'inativo').
 * - image_url 255 (legado: 45, insuficiente para URL do Cloudinary).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('title', 100)->nullable();
            $table->string('link_url', 255)->nullable();
            $table->boolean('active')->default(true)->index();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });

        Schema::create('banner_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('banner_id')->constrained('banners')->cascadeOnDelete();
            $table->string('image_url', 255);
            $table->string('cloudinary_public_id', 150)->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banner_images');
        Schema::dropIfExists('banners');
    }
};
