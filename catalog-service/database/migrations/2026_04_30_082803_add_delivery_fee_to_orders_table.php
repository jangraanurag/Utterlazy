<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('delivery_fee', 8, 2)->default(0)->after('platform_commission');
            $table->decimal('delivery_fee_customer', 8, 2)->default(0)->after('delivery_fee');
            $table->decimal('delivery_fee_restaurant', 8, 2)->default(0)->after('delivery_fee_customer');
            $table->decimal('delivery_fee_platform', 8, 2)->default(0)->after('delivery_fee_restaurant');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['delivery_fee', 'delivery_fee_customer', 'delivery_fee_restaurant', 'delivery_fee_platform']);
        });
    }
};
