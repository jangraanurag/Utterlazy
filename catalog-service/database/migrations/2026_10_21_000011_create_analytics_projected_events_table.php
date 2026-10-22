<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_projected_events', function (Blueprint $table) {
            $table->uuid('event_id')->primary();
            $table->string('table_name', 40);
            $table->timestamp('projected_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_projected_events');
    }
};
