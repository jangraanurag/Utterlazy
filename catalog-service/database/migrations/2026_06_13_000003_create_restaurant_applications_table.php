<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('restaurant_name');
            $table->string('owner_name');
            $table->string('email');
            $table->string('phone');
            $table->string('fssai_number')->nullable();
            $table->string('gst_number')->nullable();
            $table->string('pan_number')->nullable();
            $table->string('address');
            $table->string('city');
            $table->string('state');
            $table->string('pincode');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('cuisine_type')->nullable();
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->text('rejection_reason')->nullable();
            $table->json('documents')->nullable(); // uploaded document URLs
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_applications');
    }
};
