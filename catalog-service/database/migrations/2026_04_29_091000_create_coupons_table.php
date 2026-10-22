<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('title'); // e.g. "60% OFF up to ₹120"
            $table->text('description')->nullable();
            $table->enum('type', ['percentage', 'flat'])->default('percentage');
            $table->decimal('value', 8, 2); // discount percentage or flat amount
            $table->decimal('min_order', 8, 2)->default(0); // minimum order value
            $table->decimal('max_discount', 8, 2)->nullable(); // cap for percentage coupons
            $table->integer('usage_limit')->nullable(); // total times this can be used
            $table->integer('per_user_limit')->default(1);
            $table->integer('times_used')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->timestamps();
        });

        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('order_id');
            $table->decimal('discount_amount', 8, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_usages');
        Schema::dropIfExists('coupons');
    }
};
