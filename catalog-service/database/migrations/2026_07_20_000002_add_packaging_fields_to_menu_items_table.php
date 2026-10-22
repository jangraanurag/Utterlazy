<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->string('packaging_charge_level', 20)->nullable()->after('serves_count'); // item_level
            $table->string('packaging_charge_type', 20)->nullable()->after('packaging_charge_level'); // fixed
            $table->decimal('packaging_charge_value', 10, 2)->nullable()->after('packaging_charge_type');
            $table->string('packaging_tax_type', 10)->default('NONE')->after('packaging_charge_value'); // GST_5, NONE
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn(['packaging_charge_level', 'packaging_charge_type', 'packaging_charge_value', 'packaging_tax_type']);
        });
    }
};
