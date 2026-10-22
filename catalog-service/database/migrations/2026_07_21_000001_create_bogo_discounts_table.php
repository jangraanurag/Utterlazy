<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bogo_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->string('title', 255);
            $table->integer('buy_quantity')->default(1);
            $table->integer('get_quantity')->default(1);
            $table->string('user_type', 20)->default('all'); // all, new_users
            $table->time('time_from')->nullable();
            $table->time('time_to')->nullable();
            $table->boolean('all_day')->default(true);
            $table->date('start_date');
            $table->date('end_date')->nullable(); // null = no end date
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('bogo_menu_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bogo_discount_id');
            $table->foreignUuid('menu_item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->foreign('bogo_discount_id')->references('id')->on('bogo_discounts')->cascadeOnDelete();
            $table->unique(['bogo_discount_id', 'menu_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bogo_menu_items');
        Schema::dropIfExists('bogo_discounts');
    }
};
