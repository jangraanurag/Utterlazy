<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreignUuid('variant_id')->nullable()->constrained('variants')->nullOnDelete();
            $table->json('add_on_item_ids')->nullable();
            $table->string('special_instructions', 200)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropColumn(['variant_id', 'add_on_item_ids', 'special_instructions']);
        });
    }
};
