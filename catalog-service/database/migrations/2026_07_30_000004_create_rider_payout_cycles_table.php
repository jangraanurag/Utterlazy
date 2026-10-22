<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rider_payout_cycles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('rider_id')->constrained('riders')->cascadeOnDelete();
            $table->date('cycle_date');
            $table->string('status')->default('generated')->comment('generated, pending_payment, paid, cancelled');
            $table->decimal('total_orders', 10, 2)->default(0);
            $table->decimal('total_distance_km', 10, 2)->default(0);
            $table->decimal('distance_pay', 10, 2)->default(0);
            $table->decimal('base_pay', 10, 2)->default(0);
            $table->decimal('surge_pay', 10, 2)->default(0);
            $table->decimal('wait_pay', 10, 2)->default(0);
            $table->decimal('tip_amount', 10, 2)->default(0);
            $table->decimal('incentive_bonus', 10, 2)->default(0);
            $table->decimal('penalty', 10, 2)->default(0);
            $table->decimal('total_adjustments', 10, 2)->default(0);
            $table->decimal('net_payable', 10, 2)->default(0);
            $table->string('utr_number')->nullable();
            $table->date('payment_date')->nullable();
            $table->string('payment_mode')->nullable();
            $table->text('payment_notes')->nullable();
            $table->foreignUuid('entered_by')->nullable()->constrained('users');
            $table->foreignUuid('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['rider_id', 'cycle_date']);
            $table->index('status');
            $table->index('cycle_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_payout_cycles');
    }
};
