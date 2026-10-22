<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('restaurant_id');
            $table->string('type'); // percentage, flat, bogo, free_delivery
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('discount_value', 10, 2);
            $table->decimal('max_discount', 10, 2)->nullable();
            $table->decimal('min_order_value', 10, 2)->nullable();
            $table->string('applicable_type'); // all, items, categories
            $table->json('applicable_ids')->nullable();
            $table->decimal('budget', 12, 2)->nullable();
            $table->integer('used_count')->default(0);
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->json('schedule')->nullable(); // day/time slot restrictions
            $table->string('status')->default('active'); // active, paused, ended
            $table->timestamps();

            $table->foreign('restaurant_id')->references('id')->on('restaurants')->onDelete('cascade');
            $table->index(['restaurant_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('offers');
    }
};
