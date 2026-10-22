<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // AR Menu Previews
        Schema::create('ar_menu_models', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('menu_item_id');
            $table->string('model_url');
            $table->string('thumbnail_url')->nullable();
            $table->float('scale')->default(1.0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->foreign('menu_item_id')->references('id')->on('menu_items')->cascadeOnDelete();
        });

        // Kitchen Live Streams
        Schema::create('kitchen_streams', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('restaurant_id');
            $table->string('stream_url');
            $table->string('status')->default('offline'); // live, offline, scheduled
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamps();
            $table->foreign('restaurant_id')->references('id')->on('restaurants')->cascadeOnDelete();
        });

        // Crypto Payments
        Schema::create('crypto_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id');
            $table->string('currency'); // BTC, ETH, USDT
            $table->decimal('amount', 20, 8);
            $table->string('wallet_address');
            $table->string('tx_hash')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
        });

        // NFT Collectibles
        Schema::create('nft_collectibles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('image_url');
            $table->string('contract_address')->nullable();
            $table->string('token_id')->nullable();
            $table->string('rarity')->default('common'); // common, rare, epic, legendary
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // Drone Deliveries
        Schema::create('drone_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id');
            $table->string('drone_id')->nullable();
            $table->decimal('takeoff_lat', 10, 7)->nullable();
            $table->decimal('takeoff_lng', 10, 7)->nullable();
            $table->decimal('landing_lat', 10, 7)->nullable();
            $table->decimal('landing_lng', 10, 7)->nullable();
            $table->string('status')->default('scheduled');
            $table->float('estimated_flight_time_min')->nullable();
            $table->timestamps();
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drone_deliveries');
        Schema::dropIfExists('nft_collectibles');
        Schema::dropIfExists('crypto_payments');
        Schema::dropIfExists('kitchen_streams');
        Schema::dropIfExists('ar_menu_models');
    }
};
