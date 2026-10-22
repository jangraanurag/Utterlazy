<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rider_sos_alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('rider_id');
            $table->string('type')->default('sos'); // sos, accident, breakdown, theft
            $table->text('message')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status')->default('active'); // active, resolved, false_alarm
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('rider_id')->references('id')->on('riders')->cascadeOnDelete();
            $table->index(['rider_id', 'status']);
        });

        Schema::table('riders', function (Blueprint $table) {
            $table->json('emergency_contacts')->nullable()->after('badges');
            $table->boolean('safety_ride_sharing_enabled')->default(false)->after('emergency_contacts');
            $table->string('share_link')->nullable()->after('safety_ride_sharing_enabled');
            $table->timestamp('share_expires_at')->nullable()->after('share_link');
        });
    }

    public function down(): void
    {
        Schema::table('riders', function (Blueprint $table) {
            $table->dropColumn(['emergency_contacts', 'safety_ride_sharing_enabled', 'share_link', 'share_expires_at']);
        });
        Schema::dropIfExists('rider_sos_alerts');
    }
};
