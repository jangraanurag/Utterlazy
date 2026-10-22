<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opt_in_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('description')->nullable();
            $table->string('discount_type', 20); // flat, percentage
            $table->decimal('discount_value', 10, 2);
            $table->decimal('max_discount', 10, 2)->nullable();
            $table->decimal('min_order_value', 10, 2)->default(0);
            $table->decimal('share_platform', 5, 2)->default(0); // platform bears X%
            $table->decimal('share_restaurant', 5, 2)->default(100); // restaurant bears X%
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('restaurant_campaign_opt_ins', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id')->constrained('opt_in_campaigns')->cascadeOnDelete();
            $table->foreignUuid('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->boolean('opted_in')->default(false);
            $table->timestamp('opted_in_at')->nullable();
            $table->timestamp('opted_out_at')->nullable();
            $table->unique(['campaign_id', 'restaurant_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_campaign_opt_ins');
        Schema::dropIfExists('opt_in_campaigns');
    }
};
