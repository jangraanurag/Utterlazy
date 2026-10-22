<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_queues', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id')->nullable()->index();
            $table->string('call_type', 50)->default('general');
            $table->string('from_phone', 20)->nullable();
            $table->string('to_phone', 20);
            $table->json('payload')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->tinyInteger('priority')->default(5);
            $table->tinyInteger('attempts')->default(0);
            $table->tinyInteger('max_attempts')->default(3);
            $table->text('error')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_queues');
    }
};
