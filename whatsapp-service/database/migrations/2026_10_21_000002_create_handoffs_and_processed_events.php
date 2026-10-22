<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_handoffs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('wa_id', 15)->index();
            $table->uuid('order_id')->nullable();
            $table->string('reason', 300);
            $table->enum('status', ['open', 'assigned', 'closed'])->default('open')->index();
            $table->uuid('assignee_id')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('wa_processed_events', function (Blueprint $table) {
            $table->uuid('event_id');
            $table->string('consumer', 80);
            $table->timestamp('processed_at')->useCurrent();
            $table->primary(['event_id', 'consumer']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_processed_events');
        Schema::dropIfExists('wa_handoffs');
    }
};
