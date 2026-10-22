<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guard each column: tax_type may already exist from 2026_05_04_131104.
        Schema::table('menu_items', function (Blueprint $table) {
            if (! Schema::hasColumn('menu_items', 'prep_type')) {
                $table->string('prep_type', 20)->nullable()->after('description'); // Veg, Non-Veg, Vegan, Jain, Vaishno, Egg
                $table->boolean('fasting_suitable')->default(false)->after('prep_type');
                $table->integer('serves_count')->nullable()->after('fasting_suitable');
            }
            if (! Schema::hasColumn('menu_items', 'tax_type')) {
                $table->string('tax_type', 10)->default('NONE')->after('price'); // GST_5, NONE, GST_18
            }
            if (! Schema::hasColumn('menu_items', 'tax_included')) {
                $table->boolean('tax_included')->default(false)->after('tax_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn(['prep_type', 'fasting_suitable', 'tax_type', 'tax_included', 'serves_count']);
        });
    }
};
