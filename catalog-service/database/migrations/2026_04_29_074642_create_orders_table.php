<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id'); // Assuming user uses uuid or foreign key. We'll just use string/uuid for microservice flexibility
            $table->uuid('restaurant_id');
            $table->string('status')->default('pending'); // pending, paid, abandoned, rescued, preparing, out_for_delivery, delivered
            $table->decimal('subtotal', 10, 2);
            $table->decimal('tax_amount', 10, 2)->default(0); // 5% Govt Tax
            $table->decimal('vendor_discount_amount', 10, 2)->default(0); // 5% Mandatory Discount
            $table->decimal('platform_commission', 10, 2)->default(0); // 15% standard or 2% MRP
            $table->decimal('grand_total', 10, 2);
            $table->string('rescue_discount_code')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
