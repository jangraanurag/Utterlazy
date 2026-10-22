<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->decimal('refund_amount', 10, 2)->nullable()->after('vendor_replied_at');
            $table->string('refund_status')->nullable()->after('refund_amount');
            $table->timestamp('refunded_at')->nullable()->after('refund_status');
        });

        Schema::table('vendor_payments', function (Blueprint $table) {
            $table->decimal('total_refunds', 10, 2)->default(0)->after('sponsorship_gst');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn(['refund_amount', 'refund_status', 'refunded_at']);
        });

        Schema::table('vendor_payments', function (Blueprint $table) {
            $table->dropColumn('total_refunds');
        });
    }
};
