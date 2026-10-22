<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('channel', 12)->default('app')->after('status');
            $table->string('cancel_reason', 40)->nullable()->after('channel');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index(['channel', 'created_at'], 'orders_channel_created_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_channel_created_at_idx');
            $table->dropColumn(['cancel_reason', 'channel']);
        });
    }
};
