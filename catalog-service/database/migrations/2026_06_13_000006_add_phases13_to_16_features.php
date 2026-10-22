<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // NOTE: inventory_items + menu_item_inventory are created by
        // 2026_07_10_000015_create_inventory_system_tables.php (canonical schema:
        // stock_quantity/low_stock_threshold + menu_item_recipes). Not recreated here.

        // Sponsored listings
        Schema::create('sponsored_listings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('restaurant_id');
            $table->decimal('daily_budget', 10, 2);
            $table->decimal('current_spend', 10, 2)->default(0);
            $table->string('status')->default('active');
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('priority')->default(1);
            $table->timestamps();

            $table->foreign('restaurant_id')->references('id')->on('restaurants')->onDelete('cascade');
        });

        // Banner/CMS management
        Schema::create('banners', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('image_url');
            $table->string('link_type')->default('none'); // none, restaurant, category, external
            $table->string('link_value')->nullable();
            $table->string('position')->default('home_top'); // home_top, home_mid, restaurant_page
            $table->string('target_audience')->default('all'); // all, new_users, returning_users
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->timestamps();
        });

        // Push notification campaigns
        Schema::create('notification_campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('body');
            $table->string('target_audience')->default('all');
            $table->json('target_filters')->nullable();
            $table->string('status')->default('draft'); // draft, scheduled, sent, failed
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->integer('sent_count')->default(0);
            $table->integer('open_count')->default(0);
            $table->timestamps();
        });

        // City/area management
        Schema::create('cities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('state');
            $table->boolean('is_active')->default(true);
            $table->decimal('base_delivery_fee', 8, 2)->default(30);
            $table->decimal('base_commission_rate', 5, 2)->default(15);
            $table->timestamps();
        });

        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('city_id');
            $table->string('name');
            $table->json('boundary_coordinates');
            $table->decimal('delivery_fee_multiplier', 5, 2)->default(1.0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('city_id')->references('id')->on('cities')->onDelete('cascade');
        });

        // Combo offers
        Schema::create('combo_offers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('restaurant_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('items'); // [{menu_item_id, quantity}]
            $table->decimal('combo_price', 10, 2);
            $table->decimal('original_price', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('restaurant_id')->references('id')->on('restaurants')->onDelete('cascade');
        });

        // Restaurant analytics
        Schema::create('restaurant_analytics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('restaurant_id');
            $table->date('date');
            $table->integer('total_orders')->default(0);
            $table->decimal('total_revenue', 12, 2)->default(0);
            $table->decimal('avg_order_value', 8, 2)->default(0);
            $table->integer('new_customers')->default(0);
            $table->integer('returning_customers')->default(0);
            $table->decimal('avg_rating', 3, 2)->default(0);
            $table->integer('total_ratings')->default(0);
            $table->json('peak_hours')->nullable();
            $table->json('top_items')->nullable();
            $table->timestamps();

            $table->unique(['restaurant_id', 'date']);
        });

        // Customer membership
        Schema::create('memberships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('plan')->default('free'); // free, plus, pro
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(false);
            $table->json('benefits')->nullable();
            $table->timestamps();
        });

        // Referrals
        Schema::create('referrals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('referrer_id')->constrained('users');
            $table->foreignUuid('referred_id')->nullable()->constrained('users');
            $table->string('code')->unique();
            $table->string('status')->default('pending'); // pending, completed, expired
            $table->decimal('referrer_reward', 8, 2)->default(0);
            $table->decimal('referred_reward', 8, 2)->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // Gift cards
        Schema::create('gift_cards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->decimal('initial_amount', 10, 2);
            $table->decimal('remaining_amount', 10, 2);
            $table->foreignUuid('buyer_id')->constrained('users');
            $table->foreignUuid('recipient_id')->nullable()->constrained('users');
            $table->string('recipient_email')->nullable();
            $table->text('message')->nullable();
            $table->boolean('is_redeemed')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // Fraud detection logs
        Schema::create('fraud_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type'); // fake_order, gps_spoof, customer_abuse, restaurant_quality
            $table->string('entity_type'); // user, rider, restaurant
            $table->uuid('entity_id');
            $table->text('description');
            $table->json('evidence')->nullable();
            $table->string('severity')->default('low'); // low, medium, high, critical
            $table->string('status')->default('pending'); // pending, reviewed, actioned
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fraud_logs');
        Schema::dropIfExists('gift_cards');
        Schema::dropIfExists('referrals');
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('restaurant_analytics');
        Schema::dropIfExists('combo_offers');
        Schema::dropIfExists('delivery_zones');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('notification_campaigns');
        Schema::dropIfExists('banners');
        Schema::dropIfExists('sponsored_listings');
    }
};
