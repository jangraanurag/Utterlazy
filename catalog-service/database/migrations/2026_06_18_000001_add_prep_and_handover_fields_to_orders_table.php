<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('accepted_at')->nullable()->after('rider_assigned_at');
            $table->integer('prep_time_minutes')->default(0)->after('accepted_at');
            $table->timestamp('prep_deadline_at')->nullable()->after('prep_time_minutes');
            $table->timestamp('ready_marked_at')->nullable()->after('prep_deadline_at');
            $table->integer('prep_delay_minutes')->nullable()->after('ready_marked_at');
            $table->timestamp('rider_arrived_at')->nullable()->after('ready_marked_at');
            $table->integer('handover_delay_minutes')->nullable()->after('rider_arrived_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'accepted_at',
                'prep_time_minutes',
                'prep_deadline_at',
                'ready_marked_at',
                'prep_delay_minutes',
                'rider_arrived_at',
                'handover_delay_minutes',
            ]);
        });
    }
};
