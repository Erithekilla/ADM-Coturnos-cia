<?php

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
            $table->string('cpf', 255);
            $table->string('email', 191)->unique();
            $table->string('phone', 255);
            $table->date('birth_date')->nullable();
            $table->string('password', 255)->nullable();
            $table->enum('role', ['customer', 'admin', 'cliente'])->default('customer');
            $table->boolean('active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};

