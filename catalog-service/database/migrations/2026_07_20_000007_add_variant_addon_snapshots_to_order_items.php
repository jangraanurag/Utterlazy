<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->json('variant_snapshot')->nullable()->after('is_mrp');
            $table->json('addons_snapshot')->nullable()->after('variant_snapshot');
            $table->decimal('packaging_charge', 10, 2)->default(0)->after('addons_snapshot');
            $table->decimal('packaging_tax', 10, 2)->default(0)->after('packaging_charge');
            $table->decimal('item_tax', 10, 2)->default(0)->after('packaging_tax');
            $table->decimal('addon_subtotal', 10, 2)->default(0)->after('item_tax');
            $table->decimal('addon_tax', 10, 2)->default(0)->after('addon_subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn([
                'variant_snapshot', 'addons_snapshot',
                'packaging_charge', 'packaging_tax',
                'item_tax', 'addon_subtotal', 'addon_tax',
            ]);
        });
    }
};
