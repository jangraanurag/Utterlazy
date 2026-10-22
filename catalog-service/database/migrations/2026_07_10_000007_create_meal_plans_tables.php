<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('restaurant_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price_per_day', 8, 2);
            $table->decimal('price_per_week', 8, 2)->nullable();
            $table->decimal('price_per_month', 8, 2)->nullable();
            $table->unsignedInteger('meals_per_day')->default(2); // lunch + dinner
            $table->json('items')->nullable();
            $table->json('dietary_tags')->nullable();
            $table->string('cuisine_type')->nullable();
            $table->boolean('is_veg')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('restaurant_id')->references('id')->on('restaurants')->cascadeOnDelete();
            $table->index(['restaurant_id', 'is_active']);
        });

        Schema::create('meal_plan_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('meal_plan_id');
            $table->uuid('restaurant_id');
            $table->uuid('address_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('meal_time')->default('lunch_dinner'); // lunch, dinner, both
            $table->text('dietary_notes')->nullable();
            $table->decimal('total_amount', 10, 2);
            $table->string('payment_status')->default('pending'); // pending, paid, refunded
            $table->string('status')->default('active'); // active, paused, cancelled, completed
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('meal_plan_id')->references('id')->on('meal_plans')->cascadeOnDelete();
            $table->foreign('restaurant_id')->references('id')->on('restaurants');
            $table->index(['user_id', 'status']);
            $table->index(['restaurant_id', 'status']);
        });

        Schema::create('meal_plan_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('subscription_id');
            $table->date('scheduled_date');
            $table->string('meal_time'); // lunch, dinner
            $table->timestamp('delivered_at')->nullable();
            $table->string('status')->default('pending'); // pending, skipped, delivered, missed
            $table->uuid('order_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('subscription_id')->references('id')->on('meal_plan_subscriptions')->cascadeOnDelete();
            $table->unique(['subscription_id', 'scheduled_date', 'meal_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_plan_deliveries');
        Schema::dropIfExists('meal_plan_subscriptions');
        Schema::dropIfExists('meal_plans');
    }
};
