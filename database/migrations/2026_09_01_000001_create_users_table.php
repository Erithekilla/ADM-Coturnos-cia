<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            return;
        }

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 60);
            $table->string('last_name', 60);
            $table->string('cpf', 512)->nullable();
            $table->char('cpf_hash', 64)->nullable()->unique();
            $table->string('email', 191)->unique();
            $table->string('phone', 512)->nullable();
            $table->string('birth_date', 512)->nullable();
            $table->string('password', 255)->nullable();
            $table->string('google_id', 255)->nullable();
            $table->string('provider', 50)->nullable()->default('local');
            $table->string('role', 30)->default('customer');
            $table->string('status', 20)->default('ativo');
            $table->char('is_deleted', 1)->default('N');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
