<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_cycles', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->foreignUuid('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->date('cycle_start');
            $table->date('cycle_end');
            $table->timestamp('generated_at')->nullable();
            $table->string('status')->default('pending');
            $table->decimal('gross_order_value', 12, 2)->default(0);
            $table->decimal('total_deductions', 12, 2)->default(0);
            $table->decimal('total_additions', 12, 2)->default(0);
            $table->decimal('net_payable', 12, 2)->default(0);
            $table->decimal('previous_balance_carried', 12, 2)->default(0);
            $table->string('utr_number')->nullable();
            $table->date('payment_date')->nullable();
            $table->string('payment_mode')->nullable();
            $table->decimal('amount_paid', 12, 2)->nullable();
            $table->text('payment_notes')->nullable();
            $table->foreignUuid('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['restaurant_id', 'cycle_start', 'cycle_end']);
            $table->index('status');
            $table->index('generated_at');
        });

        Schema::create('payout_order_lines', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->foreignUuid('cycle_id')->constrained('payout_cycles')->cascadeOnDelete();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->decimal('order_value', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('commission', 12, 2)->default(0);
            $table->decimal('convenience_fee', 12, 2)->default(0);
            $table->decimal('delivery_charge_collected', 12, 2)->default(0);
            $table->decimal('net_settlement', 12, 2)->default(0);
            $table->string('order_status')->nullable();
            $table->timestamps();

            $table->unique('order_id');
            $table->index('cycle_id');
        });

        Schema::create('payout_deductions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->foreignUuid('cycle_id')->constrained('payout_cycles')->cascadeOnDelete();
            $table->string('type');
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('reference_type')->nullable();
            $table->uuid('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('cycle_id');
        });

        Schema::create('payout_additions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->foreignUuid('cycle_id')->constrained('payout_cycles')->cascadeOnDelete();
            $table->string('type');
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('reference_type')->nullable();
            $table->uuid('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index('cycle_id');
        });

        Schema::create('restaurant_payout_balances', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->foreignUuid('restaurant_id')->unique()->constrained('restaurants')->cascadeOnDelete();
            $table->decimal('running_balance', 12, 2)->default(0);
            $table->decimal('total_paid_to_date', 12, 2)->default(0);
            $table->decimal('total_earnings_to_date', 12, 2)->default(0);
            $table->timestamp('last_updated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('gst_liability_register', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->string('filing_period');
            $table->decimal('total_gst_collected', 12, 2)->default(0);
            $table->decimal('total_gst_deposited', 12, 2)->default(0);
            $table->string('status')->default('pending');
            $table->timestamp('deposited_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('filing_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gst_liability_register');
        Schema::dropIfExists('restaurant_payout_balances');
        Schema::dropIfExists('payout_additions');
        Schema::dropIfExists('payout_deductions');
        Schema::dropIfExists('payout_order_lines');
        Schema::dropIfExists('payout_cycles');
    }
};
