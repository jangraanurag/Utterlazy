<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catering
        Schema::create('catering_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('restaurant_id');
            $table->string('event_type'); // party, corporate, wedding
            $table->date('event_date');
            $table->integer('guest_count');
            $table->decimal('budget_per_person', 8, 2)->nullable();
            $table->text('menu_preferences')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('restaurant_id')->references('id')->on('restaurants');
        });

        // Donate a Meal
        Schema::create('meal_donations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('order_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('ngo_name')->nullable();
            $table->string('status')->default('completed');
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // Dark Kitchen / Virtual Brands
        Schema::create('dark_kitchen_brands', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('restaurant_id');
            $table->string('brand_name');
            $table->text('description')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('cuisine_type')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->foreign('restaurant_id')->references('id')->on('restaurants')->cascadeOnDelete();
        });

        // Social Feed
        Schema::create('social_posts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('order_id')->nullable();
            $table->string('type'); // food_photo, review, checkin
            $table->text('caption')->nullable();
            $table->string('image_url')->nullable();
            $table->json('tags')->nullable();
            $table->integer('likes_count')->default(0);
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('social_likes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('post_id');
            $table->uuid('user_id');
            $table->timestamps();
            $table->foreign('post_id')->references('id')->on('social_posts')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['post_id', 'user_id']);
        });

        // Leaderboards
        Schema::create('leaderboard_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('period'); // weekly, monthly, all_time
            $table->string('category'); // orders, revenue, ratings, reviews
            $table->integer('score')->default(0);
            $table->integer('rank')->default(0);
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['user_id', 'period', 'category']);
        });

        // Referral v2
        Schema::table('referrals', function (Blueprint $table) {
            $table->string('coupon_code')->nullable()->after('reward_amount');
            $table->decimal('referrer_discount', 8, 2)->default(0)->after('coupon_code');
            $table->decimal('referred_discount', 8, 2)->default(0)->after('referrer_discount');
            $table->boolean('is_influencer')->default(false)->after('referred_discount');
        });

        // Smart Cart Suggestions
        Schema::create('cart_suggestions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('menu_item_id');
            $table->uuid('suggested_item_id');
            $table->string('type'); // frequently_bought, combo, upsell
            $table->integer('score')->default(0);
            $table->timestamps();
            $table->foreign('menu_item_id')->references('id')->on('menu_items')->cascadeOnDelete();
            $table->foreign('suggested_item_id')->references('id')->on('menu_items')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_suggestions');
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropColumn(['coupon_code', 'referrer_discount', 'referred_discount', 'is_influencer']);
        });
        Schema::dropIfExists('leaderboard_entries');
        Schema::dropIfExists('social_likes');
        Schema::dropIfExists('social_posts');
        Schema::dropIfExists('dark_kitchen_brands');
        Schema::dropIfExists('meal_donations');
        Schema::dropIfExists('catering_requests');
    }
};
