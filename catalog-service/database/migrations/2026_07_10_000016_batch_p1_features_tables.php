<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sachet Pricing / Micro-subscriptions
        Schema::create('delivery_passes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('type'); // daily, weekly
            $table->decimal('price', 8, 2);
            $table->date('valid_from');
            $table->date('valid_until');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // Regional Festivals
        Schema::create('festival_campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name'); // Diwali, Eid, Pongal, etc.
            $table->string('slug');
            $table->date('start_date');
            $table->date('end_date');
            $table->text('description')->nullable();
            $table->json('regions')->nullable();
            $table->json('config')->nullable(); // banners, discounts, special menus
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Surge Pricing config
        Schema::create('surge_pricing_configs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('city')->nullable();
            $table->json('time_slots')->nullable(); // peak hour multipliers
            $table->json('weather_multipliers')->nullable();
            $table->json('demand_thresholds')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Suppliers
        Schema::create('suppliers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('restaurant_id');
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('category'); // vegetables, meat, dairy, bakery
            $table->json('items')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->foreign('restaurant_id')->references('id')->on('restaurants')->cascadeOnDelete();
        });

        // Staff Shifts
        Schema::create('staff_shifts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('restaurant_id');
            $table->uuid('staff_id');
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('status')->default('scheduled'); // scheduled, clocked_in, clocked_out, absent
            $table->timestamp('clocked_in_at')->nullable();
            $table->timestamp('clocked_out_at')->nullable();
            $table->timestamps();
            $table->foreign('restaurant_id')->references('id')->on('restaurants')->cascadeOnDelete();
            $table->index(['staff_id', 'date']);
        });

        // EV Charging Stations
        Schema::create('ev_charging_stations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('address');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->integer('available_slots')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Cash Reconciliation
        Schema::create('cash_reconciliations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('rider_id');
            $table->date('date');
            $table->decimal('expected_amount', 10, 2)->default(0);
            $table->decimal('collected_amount', 10, 2)->default(0);
            $table->decimal('deposited_amount', 10, 2)->default(0);
            $table->decimal('difference', 10, 2)->default(0);
            $table->string('status')->default('pending'); // pending, reconciled, discrepancy
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreign('rider_id')->references('id')->on('riders')->cascadeOnDelete();
            $table->unique(['rider_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_reconciliations');
        Schema::dropIfExists('ev_charging_stations');
        Schema::dropIfExists('staff_shifts');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('surge_pricing_configs');
        Schema::dropIfExists('festival_campaigns');
        Schema::dropIfExists('delivery_passes');
    }
};
