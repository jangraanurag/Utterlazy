<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_workflow_instances', function (Blueprint $table) {
            $table->decimal('pinned_max_amount', 14, 2)->nullable()->after('amount');
            $table->unsignedInteger('pinned_sla_hours')->nullable()->after('pinned_max_amount');
        });
    }

    public function down(): void
    {
        Schema::table('approval_workflow_instances', function (Blueprint $table) {
            $table->dropColumn(['pinned_max_amount', 'pinned_sla_hours']);
        });
    }
};
