<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_packaging_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->string('charge_type', 20); // fixed, percentage
            $table->decimal('charge_value', 10, 2)->default(0);
            $table->string('tax_type', 10)->default('NONE'); // GST_5, NONE
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique('restaurant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_packaging_settings');
    }
};
