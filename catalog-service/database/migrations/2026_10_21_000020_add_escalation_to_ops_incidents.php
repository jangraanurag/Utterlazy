<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ops_incidents', function (Blueprint $table) {
            $table->unsignedTinyInteger('escalation_level')->default(0)->after('status');
            $table->timestamp('last_escalated_at')->nullable()->after('sla_breached_at');
        });
    }

    public function down(): void
    {
        Schema::table('ops_incidents', function (Blueprint $table) {
            $table->dropColumn(['escalation_level', 'last_escalated_at']);
        });
    }
};
