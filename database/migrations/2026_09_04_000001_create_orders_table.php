<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            return;
        }

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->string('status', 50)->nullable();
            $table->string('tracking_code', 100)->nullable();
            $table->dateTime('order_date')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('address_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

