<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Reviews table
        Schema::create('reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->uuid('order_id')->nullable();
            $table->unsignedTinyInteger('rating');
            $table->string('title')->nullable();
            $table->text('comment');
            $table->unsignedTinyInteger('food_rating')->nullable();
            $table->unsignedTinyInteger('delivery_rating')->nullable();
            $table->unsignedTinyInteger('packaging_rating')->nullable();
            $table->json('photos')->nullable();
            $table->unsignedInteger('helpful_count')->default(0);
            $table->boolean('is_reported')->default(false);
            $table->text('report_reason')->nullable();
            $table->timestamps();
        });

        // Scheduled orders table
        Schema::create('scheduled_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('restaurant_id')->constrained('restaurants');
            $table->json('items');
            $table->uuid('address_id');
            $table->timestamp('scheduled_at');
            $table->string('payment_mode')->default('prepaid');
            $table->string('coupon_code')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('scheduled');
            $table->uuid('order_id')->nullable();
            $table->timestamps();
        });

        // Group orders table
        Schema::create('group_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('host_user_id')->constrained('users');
            $table->foreignUuid('restaurant_id')->constrained('restaurants');
            $table->string('invite_code')->unique();
            $table->timestamp('scheduled_at')->nullable();
            $table->string('status')->default('collecting');
            $table->unsignedInteger('participant_count')->default(1);
            $table->timestamps();
        });

        // Group order items table
        Schema::create('group_order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('group_order_id')->constrained('group_orders')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users');
            $table->foreignUuid('menu_item_id')->constrained('menu_items');
            $table->integer('quantity')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_order_items');
        Schema::dropIfExists('group_orders');
        Schema::dropIfExists('scheduled_orders');
        Schema::dropIfExists('reviews');
    }
};
