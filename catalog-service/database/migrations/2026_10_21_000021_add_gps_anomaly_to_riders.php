<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('riders', function (Blueprint $table) {
            $table->unsignedSmallInteger('gps_anomaly_count')->default(0)->after('longitude');
            $table->timestamp('gps_flagged_at')->nullable()->after('gps_anomaly_count');
        });
    }

    public function down(): void
    {
        Schema::table('riders', function (Blueprint $table) {
            $table->dropColumn(['gps_anomaly_count', 'gps_flagged_at']);
        });
    }
};
