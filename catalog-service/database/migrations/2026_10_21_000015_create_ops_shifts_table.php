<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ops_shifts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->index();
            $table->uuid('org_unit_id')->nullable()->index();
            $table->uuid('city_id')->nullable()->index();
            $table->string('role', 40)->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('status', 20)->default('scheduled')->index();
            $table->string('notes', 200)->nullable();
            $table->string('cancelled_reason', 200)->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'starts_at']);
            $table->index(['starts_at', 'ends_at']);
            $table->index(['city_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_shifts');
    }
};
