<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Column already added in 2026_07_10_000012 — guard for fresh installs.
        if (! Schema::hasColumn('riders', 'delivery_mode')) {
            Schema::table('riders', function (Blueprint $table) {
                $table->string('delivery_mode')->default('scooter')->after('upi_id');
            });
        }
    }

    public function down(): void
    {
        // Owned by 2026_07_10_000012; only drop if present.
        if (Schema::hasColumn('riders', 'delivery_mode')) {
            Schema::table('riders', function (Blueprint $table) {
                $table->dropColumn('delivery_mode');
            });
        }
    }
};
