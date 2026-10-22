<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->uuid('city_id')->nullable()->after('district');
            $table->index('city_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->uuid('city_id')->nullable()->after('restaurant_id');
            $table->index(['city_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['city_id', 'created_at']);
            $table->dropColumn('city_id');
        });

        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropIndex(['city_id']);
            $table->dropColumn('city_id');
        });
    }
};
