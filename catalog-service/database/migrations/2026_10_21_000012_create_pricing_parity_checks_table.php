<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_parity_checks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id')->index();
            $table->uuid('city_id')->nullable()->index();
            $table->uuid('restaurant_id')->nullable();
            $table->boolean('matched')->default(false)->index();
            $table->json('diffs')->nullable();
            $table->json('laravel')->nullable();
            $table->json('java')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['created_at', 'matched']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_parity_checks');
    }
};
