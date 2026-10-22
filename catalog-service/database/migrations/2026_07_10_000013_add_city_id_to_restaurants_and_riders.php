<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('restaurants', 'city_id')) {
            Schema::table('restaurants', function (Blueprint $table) {
                $table->uuid('city_id')->nullable()->after('auto_accept_orders');
                $table->foreign('city_id')->references('id')->on('cities')->nullOnDelete();
            });
        }
        if (! Schema::hasColumn('riders', 'city_id')) {
            Schema::table('riders', function (Blueprint $table) {
                $table->uuid('city_id')->nullable()->after('preferred_zones');
                $table->foreign('city_id')->references('id')->on('cities')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropForeign(['city_id']);
            $table->dropColumn('city_id');
        });
        Schema::table('riders', function (Blueprint $table) {
            $table->dropForeign(['city_id']);
            $table->dropColumn('city_id');
        });
    }
};
