<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_placement_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('placement_id')->index();
            $table->uuid('city_id')->nullable()->index();
            $table->enum('event_type', ['impression', 'click']);
            $table->string('external_id', 80)->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['placement_id', 'event_type', 'external_id'], 'wa_placement_event_dedupe');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_placement_events');
    }
};
