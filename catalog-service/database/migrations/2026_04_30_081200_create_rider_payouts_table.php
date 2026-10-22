<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rider_payouts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('rider_id');
            $table->uuid('order_id');
            $table->decimal('base_pay', 8, 2)->default(0);
            $table->decimal('distance_pay', 8, 2)->default(0);
            $table->decimal('surge_pay', 8, 2)->default(0);
            $table->decimal('wait_pay', 8, 2)->default(0);
            $table->decimal('tip_amount', 8, 2)->default(0);
            $table->decimal('incentive_bonus', 8, 2)->default(0);
            $table->decimal('penalty', 8, 2)->default(0);
            $table->decimal('cash_collected', 8, 2)->default(0);
            $table->decimal('total_payout', 8, 2)->default(0);
            $table->decimal('surge_multiplier', 3, 2)->default(1.00);
            $table->boolean('is_settled')->default(false);
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->foreign('rider_id')->references('id')->on('riders')->cascadeOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->index(['rider_id', 'is_settled']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_payouts');
    }
};
