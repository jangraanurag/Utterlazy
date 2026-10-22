<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'razorpay_order_id')) {
                $table->string('razorpay_order_id', 100)->nullable()->after('grand_total');
            }
            if (! Schema::hasColumn('orders', 'razorpay_payment_id')) {
                $table->string('razorpay_payment_id', 100)->nullable()->after('razorpay_order_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['razorpay_order_id', 'razorpay_payment_id']);
        });
    }
};
