<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('customer_unreachable_at')->nullable()->after('cancel_note');
        });

        Schema::table('rider_assignments', function (Blueprint $table) {
            $table->string('rejection_reason', 40)->nullable();
        });

        Schema::table('riders', function (Blueprint $table) {
            $table->timestamp('deactivated_at')->nullable();
            $table->string('deactivation_reason', 200)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('customer_unreachable_at');
        });

        Schema::table('rider_assignments', function (Blueprint $table) {
            $table->dropColumn('rejection_reason');
        });

        Schema::table('riders', function (Blueprint $table) {
            $table->dropColumn(['deactivated_at', 'deactivation_reason']);
        });
    }
};
