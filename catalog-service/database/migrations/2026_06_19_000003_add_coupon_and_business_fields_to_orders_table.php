<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Coupon fields (needed to fix checkout failures)
            if (! Schema::hasColumn('orders', 'coupon_id')) {
                $table->foreignUuid('coupon_id')->nullable()->constrained('coupons')->nullOnDelete()->after('rescue_discount_code');
            }
            if (! Schema::hasColumn('orders', 'coupon_discount_amount')) {
                $table->decimal('coupon_discount_amount', 10, 2)->default(0.00)->after('coupon_id');
            }

            // Business ordering fields
            if (! Schema::hasColumn('orders', 'is_business_order')) {
                $table->boolean('is_business_order')->default(false)->after('coupon_discount_amount');
            }
            if (! Schema::hasColumn('orders', 'company_name')) {
                $table->string('company_name')->nullable()->after('is_business_order');
            }
            if (! Schema::hasColumn('orders', 'company_gst')) {
                $table->string('company_gst', 20)->nullable()->after('company_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'coupon_id',
                'coupon_discount_amount',
                'is_business_order',
                'company_name',
                'company_gst',
            ]);
        });
    }
};
