<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->uuidMorphs('tokenable'); // polymorphic: user or vendor or rider
            $table->string('token', 512);
            $table->string('platform', 10); // android or ios
            $table->string('app_type', 20); // customer, restaurant, rider
            $table->string('device_name')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index('app_type');
            $table->index('token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
