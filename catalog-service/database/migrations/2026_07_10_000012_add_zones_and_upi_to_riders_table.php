<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('riders', function (Blueprint $table) {
            $table->json('preferred_zones')->nullable()->after('share_expires_at');
            $table->string('upi_id')->nullable()->after('preferred_zones');
            $table->string('delivery_mode')->default('scooter')->after('upi_id');
        });
    }

    public function down(): void
    {
        Schema::table('riders', function (Blueprint $table) {
            $table->dropColumn(['preferred_zones', 'upi_id']);
        });
    }
};
