<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('add_on_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('selection_type', 20)->default('multiple'); // single, multiple
            $table->integer('min_select')->default(0);
            $table->integer('max_select')->default(0); // 0 = unlimited
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('add_on_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('add_on_group_id')->constrained('add_on_groups')->cascadeOnDelete();
            $table->string('name', 100);
            $table->decimal('price', 10, 2);
            $table->string('tax_type', 10)->default('NONE'); // GST_5, NONE, GST_18
            $table->boolean('is_available')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('menu_item_add_on_group', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('menu_item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->foreignUuid('add_on_group_id')->constrained('add_on_groups')->cascadeOnDelete();
            $table->integer('sort_order')->default(0);
            $table->unique(['menu_item_id', 'add_on_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_add_on_group');
        Schema::dropIfExists('add_on_items');
        Schema::dropIfExists('add_on_groups');
    }
};
