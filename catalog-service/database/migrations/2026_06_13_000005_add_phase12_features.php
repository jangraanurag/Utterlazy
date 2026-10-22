<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add fields to restaurants
        Schema::table('restaurants', function (Blueprint $table) {
            $table->decimal('minimum_order_amount', 8, 2)->default(0)->after('is_active');
            $table->decimal('delivery_radius_km', 5, 2)->default(10.0)->after('minimum_order_amount');
            $table->json('photos')->nullable()->after('delivery_radius_km');
            $table->string('cuisine_type')->nullable()->after('photos');
            $table->decimal('price_for_two', 8, 2)->nullable()->after('cuisine_type');
        });

        // Add fields to menu_items
        Schema::table('menu_items', function (Blueprint $table) {
            $table->boolean('is_bestseller')->default(false)->after('is_available');
            $table->boolean('is_new')->default(false)->after('is_bestseller');
            $table->unsignedInteger('preparation_time_minutes')->nullable()->after('is_new');
            $table->unsignedTinyInteger('spice_level')->default(0)->after('preparation_time_minutes');
            $table->json('variants')->nullable()->after('spice_level');
            $table->json('add_ons')->nullable()->after('variants');
        });

        // Add special_instructions to order_items
        Schema::table('order_items', function (Blueprint $table) {
            $table->text('special_instructions')->nullable()->after('total_price');
        });

        // User recent searches
        Schema::create('user_searches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('query');
            $table->timestamps();
        });

        // Notification preferences
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->boolean('order_updates')->default(true);
            $table->boolean('offers')->default(true);
            $table->boolean('promotions')->default(true);
            $table->boolean('new_restaurants')->default(true);
            $table->boolean('rating_reminders')->default(true);
            $table->timestamps();
        });

        // Restaurant staff
        Schema::create('restaurant_staff', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('restaurant_id');
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('role')->default('staff'); // owner, manager, kitchen, staff
            $table->json('permissions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('restaurant_id')->references('id')->on('restaurants')->onDelete('cascade');
        });

        // Extend coupons for global offers
        Schema::table('coupons', function (Blueprint $table) {
            $table->boolean('auto_apply')->default(false)->after('per_user_limit');
            $table->boolean('restaurant_selectable')->default(false)->after('auto_apply');
            $table->decimal('offer_share_platform', 5, 2)->default(100)->after('restaurant_selectable');
            $table->decimal('offer_share_restaurant', 5, 2)->default(0)->after('offer_share_platform');
            $table->string('offer_category')->default('general')->after('offer_share_restaurant');
            $table->json('applicable_restaurants')->nullable()->after('offer_category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_staff');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('user_searches');
    }
};
