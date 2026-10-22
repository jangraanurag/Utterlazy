<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sponsored_listings')) {
            Schema::create('sponsored_listings', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
                $table->decimal('daily_budget', 10, 2);
                $table->decimal('current_spend', 10, 2)->default(0);
                $table->enum('status', ['active', 'paused', 'ended'])->default('active');
                $table->date('start_date');
                $table->date('end_date');
                $table->integer('priority')->default(5);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsored_listings');
    }
};
