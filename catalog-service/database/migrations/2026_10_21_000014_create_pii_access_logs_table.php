<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pii_access_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('actor_user_id')->index();
            $table->string('actor_role', 40)->nullable();
            $table->string('action', 20); // view | export | download
            $table->string('resource_type', 40);
            $table->uuid('resource_id')->nullable();
            $table->json('filters')->nullable();
            $table->unsignedInteger('row_count')->nullable();
            $table->string('purpose', 200)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['resource_type', 'created_at']);
            $table->index(['actor_user_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pii_access_logs');
    }
};
