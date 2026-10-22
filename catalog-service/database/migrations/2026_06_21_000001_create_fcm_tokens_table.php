<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fcm_tokens')) {
            return;
        }
        Schema::create('fcm_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete()->index();
            $table->foreignUuid('restaurant_id')->nullable()->constrained('restaurants')->nullOnDelete();
            $table->string('token', 512)->index();
            $table->string('platform', 10);
            $table->string('app_type', 20);
            $table->string('device_name')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'app_type']);
            $table->index(['restaurant_id', 'app_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fcm_tokens');
    }
};
