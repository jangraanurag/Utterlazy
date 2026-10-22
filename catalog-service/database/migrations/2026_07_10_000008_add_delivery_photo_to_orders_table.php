<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_photo_path')->nullable()->after('is_business_order');
            $table->timestamp('photo_taken_at')->nullable()->after('delivery_photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['delivery_photo_path', 'photo_taken_at']);
        });
    }
};
