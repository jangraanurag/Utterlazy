<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            if (! Schema::hasColumn('menu_items', 'global_category_id')) {
                $table->foreignUuid('global_category_id')->nullable()->after('category_id')->constrained('global_categories')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropForeign(['global_category_id']);
            $table->dropColumn('global_category_id');
        });
    }
};
