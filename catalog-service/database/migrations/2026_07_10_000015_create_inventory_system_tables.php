<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->boolean('inventory_enabled')->default(false)->after('pos_integration');
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('restaurant_id');
            $table->string('name');
            $table->string('unit'); // kg, litre, pcs, packet, g, ml
            $table->decimal('stock_quantity', 12, 3)->default(0);
            $table->decimal('low_stock_threshold', 12, 3)->default(0);
            $table->decimal('cost_per_unit', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('restaurant_id')->references('id')->on('restaurants')->cascadeOnDelete();
            $table->index(['restaurant_id', 'is_active']);
        });

        Schema::create('menu_item_recipes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('restaurant_id');
            $table->uuid('menu_item_id');
            $table->uuid('inventory_item_id');
            $table->decimal('quantity_required', 12, 3); // e.g., 50 for 50g mayo per burger
            $table->timestamps();

            $table->foreign('restaurant_id')->references('id')->on('restaurants')->cascadeOnDelete();
            $table->foreign('menu_item_id')->references('id')->on('menu_items')->cascadeOnDelete();
            $table->foreign('inventory_item_id')->references('id')->on('inventory_items')->cascadeOnDelete();
            $table->unique(['menu_item_id', 'inventory_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_recipes');
        Schema::dropIfExists('inventory_items');
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn('inventory_enabled');
        });
    }
};
