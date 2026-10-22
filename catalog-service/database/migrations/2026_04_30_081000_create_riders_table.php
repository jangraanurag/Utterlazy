<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->unique();
            $table->string('password');
            $table->enum('vehicle_type', ['bicycle', 'scooter', 'bike', 'car'])->default('bike');
            $table->string('vehicle_number')->nullable();
            $table->string('profile_photo')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_online')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->uuid('current_order_id')->nullable();
            $table->decimal('avg_rating', 3, 2)->default(5.00);
            $table->unsignedInteger('total_deliveries')->default(0);
            $table->decimal('total_earnings', 12, 2)->default(0);
            $table->decimal('acceptance_rate', 5, 2)->default(100.00);
            $table->timestamps();

            $table->index(['is_online', 'is_verified']);
            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riders');
    }
};
