<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_outlet_metrics', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->foreignUuid('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->date('date');
            $table->string('overview_category', 50);
            $table->string('metric_name', 100);
            $table->decimal('value', 16, 2)->default(0);
            $table->timestamps();
            $table->unique(['restaurant_id', 'date', 'overview_category', 'metric_name'], 'daily_metric_unique');
            $table->index(['restaurant_id', 'date']);
            $table->index('date');
        });

        Schema::create('daily_outlet_item_metrics', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->foreignUuid('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->foreignUuid('menu_item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->date('date');
            $table->integer('orders')->default(0);
            $table->decimal('revenue', 12, 2)->default(0);
            $table->timestamps();
            $table->unique(['restaurant_id', 'menu_item_id', 'date'], 'daily_item_metric_unique');
            $table->index(['restaurant_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_outlet_item_metrics');
        Schema::dropIfExists('daily_outlet_metrics');
    }
};
