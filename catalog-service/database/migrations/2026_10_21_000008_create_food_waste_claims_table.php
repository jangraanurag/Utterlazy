<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_waste_claims', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id')->index();
            $table->uuid('restaurant_id')->index();
            $table->decimal('amount', 12, 2);
            $table->string('reason', 300);
            $table->string('photo_path')->nullable();
            $table->enum('status', ['draft', 'pending_review', 'approved', 'rejected'])->default('draft')->index();
            $table->uuid('approval_instance_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_waste_claims');
    }
};
