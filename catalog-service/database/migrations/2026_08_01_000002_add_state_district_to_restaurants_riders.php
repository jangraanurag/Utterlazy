<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->string('state')->nullable()->after('address');
            $table->string('district')->nullable()->after('state');
            $table->index(['state', 'district']);
        });

        Schema::table('riders', function (Blueprint $table) {
            $table->string('state')->nullable()->after('delivery_mode');
            $table->string('district')->nullable()->after('state');
            $table->index(['state', 'district']);
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropIndex(['state', 'district']);
            $table->dropColumn(['state', 'district']);
        });

        Schema::table('riders', function (Blueprint $table) {
            $table->dropIndex(['state', 'district']);
            $table->dropColumn(['state', 'district']);
        });
    }
};
