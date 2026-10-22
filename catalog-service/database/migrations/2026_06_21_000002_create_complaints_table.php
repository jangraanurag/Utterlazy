<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->index();
            $table->uuid('order_id');
            $table->uuid('restaurant_id');
            $table->string('reason');
            $table->text('description');
            $table->string('status')->default('pending');
            $table->text('vendor_response')->nullable();
            $table->decimal('refund_amount', 10, 2)->nullable();
            $table->boolean('refund_processed')->default(false);
            $table->timestamps();

            $table->index(['restaurant_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        Schema::table('complaints', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('restaurant_id')->references('id')->on('restaurants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
