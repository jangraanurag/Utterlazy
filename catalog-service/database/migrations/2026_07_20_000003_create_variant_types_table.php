<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('variant_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('menu_item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->string('axis', 20); // size, volume, quantity
            $table->string('unit', 20)->nullable(); // inch, cm, ml, ltr (null for quantity)
            $table->unique('menu_item_id');
            $table->timestamps();
        });

        Schema::create('variants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('variant_type_id')->constrained('variant_types')->cascadeOnDelete();
            $table->string('label', 100); // "Half Plate", "8 inch"
            $table->string('unit', 20)->nullable(); // inherited from type or overridden
            $table->decimal('price_impact', 10, 2)->default(0);
            $table->boolean('is_default')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variants');
        Schema::dropIfExists('variant_types');
    }
};
