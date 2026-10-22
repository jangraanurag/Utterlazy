<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projected_orders', function (Blueprint $table) {
            $table->string('order_id', 36)->primary();
            $table->uuid('user_id')->nullable()->index();
            $table->uuid('restaurant_id')->nullable()->index();
            $table->uuid('city_id')->nullable()->index();
            $table->string('channel', 12)->default('app');
            $table->string('status', 30);
            $table->string('cancel_reason', 40)->nullable();
            $table->decimal('grand_total', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('vendor_discount', 12, 2)->default(0);
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->decimal('platform_fee', 12, 2)->default(0);
            $table->decimal('coupon_discount', 12, 2)->default(0);
            $table->decimal('commission', 12, 2)->default(0);
            $table->decimal('commission_gst', 12, 2)->default(0);
            $table->decimal('net_payout', 12, 2)->default(0);
            $table->json('pricing')->nullable();
            $table->unsignedBigInteger('version')->default(0);
            $table->string('event_type', 80)->nullable();
            $table->string('last_event_id', 36)->nullable();
            $table->timestamp('last_event_at')->nullable();
            $table->timestamp('projected_at')->useCurrent()->index();

            $table->index(['status', 'projected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projected_orders');
    }
};
