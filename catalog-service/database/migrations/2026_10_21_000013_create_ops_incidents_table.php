<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ops_incidents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('city_id')->nullable()->index();
            $table->string('severity', 8);
            $table->string('category', 30);
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('open')->index();
            $table->uuid('owner_user_id')->nullable();
            $table->uuid('opened_by')->nullable();
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('ack_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('sla_due_at')->nullable()->index();
            $table->timestamp('sla_breached_at')->nullable();
            $table->string('resolution_note', 500)->nullable();
            $table->timestamps();

            $table->index(['status', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_incidents');
    }
};
