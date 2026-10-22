<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('rider_id')->constrained('riders')->cascadeOnDelete();
            $table->integer('stop_count')->default(0);
            $table->decimal('total_distance_km', 10, 2)->default(0);
            $table->decimal('total_earnings', 12, 2)->default(0);
            $table->string('status')->default('active');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('rider_id');
            $table->index('status');
        });

        Schema::create('trip_stops', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->integer('stop_order');
            $table->string('stop_type'); // 'pickup' or 'delivery'
            $table->string('status')->default('pending');
            $table->decimal('distance_from_prev_km', 10, 2)->default(0);
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_stops');
        Schema::dropIfExists('trips');
    }
};
