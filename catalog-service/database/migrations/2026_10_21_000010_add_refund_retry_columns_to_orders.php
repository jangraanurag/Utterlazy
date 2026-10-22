<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedSmallInteger('refund_attempts')->default(0)->after('payment_status');
            $table->text('refund_last_error')->nullable()->after('refund_attempts');
            $table->timestamp('refund_retried_at')->nullable()->after('refund_last_error');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index(['payment_status', 'refund_attempts'], 'orders_refund_pending_idx');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_refund_pending_idx');
            $table->dropColumn(['refund_attempts', 'refund_last_error', 'refund_retried_at']);
        });
    }
};
