<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('restaurant_id');
            $table->string('type'); // commission, sponsorship
            $table->string('invoice_number')->unique();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('amount', 12, 2);
            $table->decimal('gst_rate', 5, 2)->default(18.00);
            $table->decimal('gst_amount', 12, 2);
            $table->decimal('total_amount', 12, 2);
            $table->decimal('commission_rate', 5, 2)->nullable();
            $table->string('status')->default('pending'); // pending, approved, paid, cancelled
            $table->text('admin_notes')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->foreign('restaurant_id')->references('id')->on('restaurants')->onDelete('cascade');
        });

        Schema::create('vendor_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('restaurant_id');
            $table->string('payment_period'); // e.g., '2026-06'
            $table->date('week_start');
            $table->date('week_end');
            $table->decimal('total_order_value', 12, 2)->default(0);
            $table->decimal('total_discounts', 12, 2)->default(0);
            $table->decimal('total_commission', 12, 2)->default(0);
            $table->decimal('commission_gst', 12, 2)->default(0);
            $table->decimal('total_sponsorship', 12, 2)->default(0);
            $table->decimal('sponsorship_gst', 12, 2)->default(0);
            $table->decimal('net_payable', 12, 2)->default(0);
            $table->string('status')->default('pending_approval'); // pending_approval, approved, completed
            $table->text('admin_notes')->nullable();
            $table->string('utr_number')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->foreign('restaurant_id')->references('id')->on('restaurants')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_payments');
        Schema::dropIfExists('invoices');
    }
};
