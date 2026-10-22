<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_placements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('restaurant_id');
            $table->uuid('city_id');
            $table->decimal('daily_budget', 12, 2);
            $table->decimal('cpc_bid', 12, 2);
            $table->decimal('spent_today', 12, 2)->default(0);
            $table->date('spent_date')->nullable();
            $table->timestamp('start_at');
            $table->timestamp('end_at');
            $table->string('creative_title', 40);
            $table->string('creative_body', 120);
            $table->enum('status', ['draft', 'pending_review', 'active', 'paused', 'exhausted', 'rejected'])
                ->default('draft')->index();
            $table->decimal('pinned_floor', 12, 2)->nullable();
            $table->uuid('approval_instance_id')->nullable();
            $table->string('rejection_reason', 300)->nullable();
            $table->timestamps();

            $table->index(['city_id', 'status']);
            $table->index(['restaurant_id', 'status']);
        });

        Schema::create('wa_city_floors', function (Blueprint $table) {
            $table->uuid('city_id')->primary();
            $table->decimal('floor_cpc', 12, 2)->default(5.00);
            $table->unsignedSmallInteger('max_slots')->default(5);
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_city_floors');
        Schema::dropIfExists('wa_placements');
    }
};
