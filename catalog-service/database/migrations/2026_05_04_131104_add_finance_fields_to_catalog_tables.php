<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('menu_items', 'gst_percent')) {
            Schema::table('menu_items', function (Blueprint $table) {
                $table->decimal('gst_percent', 5, 2)->default(5.00)->after('price');
                $table->string('hsn_code', 20)->nullable()->after('gst_percent');
                $table->enum('tax_type', ['inclusive', 'exclusive'])->default('inclusive')->after('hsn_code');
            });
        }

        if (! Schema::hasColumn('orders', 'platform_fee')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->decimal('platform_fee', 10, 2)->default(0.00)->after('tax_amount');
                $table->decimal('net_payout', 10, 2)->default(0.00)->after('platform_fee');
                $table->json('gst_details')->nullable()->after('net_payout');
            });
        }
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn(['gst_percent', 'hsn_code', 'tax_type']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['platform_fee', 'net_payout', 'gst_details']);
        });
    }
};
