<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->json('pos_integration')->nullable()->after('auto_accept_orders');
            $table->string('pos_sync_status')->default('not_configured')->after('pos_integration');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('pos_sync_status')->default('not_configured')->after('photo_taken_at');
            $table->timestamp('pos_synced_at')->nullable()->after('pos_sync_status');
            $table->string('pos_order_id')->nullable()->after('pos_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['pos_sync_status', 'pos_synced_at', 'pos_order_id']);
        });
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn(['pos_integration', 'pos_sync_status']);
        });
    }
};
