<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('event_id')->unique();
            $table->string('aggregate_type', 50)->index();
            $table->uuid('aggregate_id')->index();
            $table->string('event_type', 80)->index();
            $table->json('payload');
            $table->uuid('city_id')->nullable()->index();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamp('published_at')->nullable()->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
        });

        Schema::create('processed_events', function (Blueprint $table) {
            $table->uuid('event_id');
            $table->string('consumer', 80);
            $table->timestamp('processed_at')->useCurrent();
            $table->primary(['event_id', 'consumer']);
        });

        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->string('key', 80);
            $table->uuid('user_id');
            $table->uuid('order_id')->nullable();
            $table->json('response');
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['key', 'user_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('processed_events');
        Schema::dropIfExists('outbox');
    }
};
