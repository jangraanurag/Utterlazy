<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dietary filters + calorie info on menu items
        Schema::table('menu_items', function (Blueprint $table) {
            $table->json('dietary_tags')->nullable()->after('name_local'); // ["jain", "satvik", "vegan", "gluten_free", "keto"]
            $table->integer('calories')->nullable()->after('dietary_tags');
            $table->json('nutrition_info')->nullable()->after('calories'); // {"protein":"12g","carbs":"30g","fat":"15g"}
        });

        // Price drop alerts
        Schema::create('price_drop_alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('menu_item_id');
            $table->decimal('target_price', 8, 2);
            $table->string('status')->default('active'); // active, triggered, cancelled
            $table->timestamp('triggered_at')->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('menu_item_id')->references('id')->on('menu_items')->cascadeOnDelete();
            $table->unique(['user_id', 'menu_item_id']);
        });

        // Spin the wheel rewards
        Schema::create('spin_rewards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('reward_type'); // discount, free_delivery, coins, item
            $table->string('reward_value'); // "20%", "₹50", "100 coins", "Free Gulab Jamun"
            $table->decimal('discount_percent', 5, 2)->nullable();
            $table->decimal('discount_amount', 8, 2)->nullable();
            $table->string('coupon_code')->nullable();
            $table->string('status')->default('active'); // active, used, expired
            $table->timestamp('used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // Customer insights table (vendor analytics enhancement)
        Schema::create('customer_insights', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('restaurant_id');
            $table->uuid('user_id');
            $table->integer('order_count')->default(0);
            $table->decimal('total_spent', 10, 2)->default(0);
            $table->timestamp('last_order_at')->nullable();
            $table->timestamps();
            $table->foreign('restaurant_id')->references('id')->on('restaurants')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['restaurant_id', 'user_id']);
        });

        // QR table ordering
        Schema::create('qr_tables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('restaurant_id');
            $table->string('table_number');
            $table->string('qr_code_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->foreign('restaurant_id')->references('id')->on('restaurants')->cascadeOnDelete();
            $table->unique(['restaurant_id', 'table_number']);
        });

        // Self pickup slots
        Schema::create('pickup_slots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id')->unique();
            $table->uuid('restaurant_id');
            $table->uuid('user_id');
            $table->timestamp('pickup_by');
            $table->string('status')->default('scheduled'); // scheduled, picked_up, missed
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamps();
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('restaurant_id')->references('id')->on('restaurants');
        });

        // Demand heat map events
        Schema::create('demand_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('area_name')->nullable();
            $table->integer('order_count')->default(1);
            $table->string('period'); // hourly
            $table->timestamp('recorded_at');
            $table->index(['period', 'recorded_at']);
        });

        // Competitor prices (vendor benchmark)
        Schema::create('competitor_prices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('restaurant_id');
            $table->uuid('menu_item_id');
            $table->decimal('competitor_price', 8, 2);
            $table->string('competitor_name')->nullable();
            $table->timestamps();
            $table->foreign('restaurant_id')->references('id')->on('restaurants')->cascadeOnDelete();
            $table->foreign('menu_item_id')->references('id')->on('menu_items')->cascadeOnDelete();
            $table->unique(['restaurant_id', 'menu_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitor_prices');
        Schema::dropIfExists('demand_events');
        Schema::dropIfExists('pickup_slots');
        Schema::dropIfExists('qr_tables');
        Schema::dropIfExists('customer_insights');
        Schema::dropIfExists('spin_rewards');
        Schema::dropIfExists('price_drop_alerts');
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn(['dietary_tags', 'calories', 'nutrition_info']);
        });
    }
};
