<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_rider_metrics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('rider_id')->constrained('riders')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('orders_completed')->default(0);
            $table->unsignedInteger('orders_cancelled')->default(0);
            $table->decimal('total_earnings', 10, 2)->default(0);
            $table->decimal('total_distance_km', 8, 2)->default(0);
            $table->unsignedInteger('total_online_minutes')->default(0);

            $table->json('restaurant_breakdown')->nullable()->comment('{"restaurant_id": count}');
            $table->json('time_slot_breakdown')->nullable()->comment('{"0-3": count, "3-6": count, ...}');
            $table->json('hourly_earnings')->nullable()->comment('{"hour": earnings}');

            $table->timestamps();

            $table->unique(['rider_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_rider_metrics');
    }
};
