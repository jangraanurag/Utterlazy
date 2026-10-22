<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rider_ratings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('rider_id')->constrained('riders')->cascadeOnDelete();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->uuid('user_id');
            $table->tinyInteger('rating'); // 1-5
            $table->text('feedback')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['order_id', 'user_id']); // One rating per order per user
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_ratings');
    }
};
