<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['status', 'rider_id', 'created_at'], 'orders_assignable_idx');
        });

        Schema::table('pending_assignments', function (Blueprint $table) {
            $table->index('created_at');
        });

        Schema::table('rider_assignments', function (Blueprint $table) {
            $table->index(['status', 'assigned_at'], 'rider_assignments_stale_idx');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_assignable_idx');
        });

        Schema::table('pending_assignments', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });

        Schema::table('rider_assignments', function (Blueprint $table) {
            $table->dropIndex('rider_assignments_stale_idx');
        });
    }
};
