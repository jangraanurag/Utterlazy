<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            // ─── Vendor Auth ───
            $table->string('email')->nullable()->unique()->after('name');
            $table->string('password')->nullable()->after('email');
            $table->string('phone', 20)->nullable()->after('password');

            // ─── Compliance (CA Requirements) ───
            $table->string('fssai_number', 20)->nullable()->after('address');
            $table->string('gst_number', 20)->nullable()->after('fssai_number');
            $table->string('pan_number', 12)->nullable()->after('gst_number');

            // ─── Bank Details ───
            $table->string('bank_account_number')->nullable()->after('pan_number');
            $table->string('bank_ifsc', 15)->nullable()->after('bank_account_number');
            $table->string('bank_name')->nullable()->after('bank_ifsc');
            $table->string('bank_holder_name')->nullable()->after('bank_name');

            // ─── Operational ───
            $table->boolean('is_accepting_orders')->default(true)->after('is_active');
            $table->integer('avg_prep_time_minutes')->default(25)->after('is_accepting_orders');

            // ─── Stats & Financials ───
            $table->integer('total_orders_completed')->default(0)->after('avg_prep_time_minutes');
            $table->decimal('total_revenue', 12, 2)->default(0)->after('total_orders_completed');
            $table->decimal('commission_rate', 5, 2)->default(15.00)->after('total_revenue');
            $table->decimal('delivery_fee_share', 5, 2)->default(25.00)->after('commission_rate');

            // ─── Audit ───
            $table->timestamp('last_login_at')->nullable()->after('delivery_fee_share');
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn([
                'email', 'password', 'phone',
                'fssai_number', 'gst_number', 'pan_number',
                'bank_account_number', 'bank_ifsc', 'bank_name', 'bank_holder_name',
                'is_accepting_orders', 'avg_prep_time_minutes',
                'total_orders_completed', 'total_revenue',
                'commission_rate', 'delivery_fee_share',
                'last_login_at',
            ]);
        });
    }
};
