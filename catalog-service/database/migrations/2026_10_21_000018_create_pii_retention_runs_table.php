<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pii_retention_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('actor_user_id')->nullable()->index();
            $table->timestamp('cutoff');
            $table->boolean('dry_run')->default(true);
            $table->unsignedInteger('rows_deleted')->default(0);
            $table->json('breakdown')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pii_retention_runs');
    }
};
