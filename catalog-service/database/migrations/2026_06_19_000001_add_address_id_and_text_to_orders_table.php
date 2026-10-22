<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignUuid('address_id')->nullable()->constrained('addresses')->nullOnDelete()->after('restaurant_id');
            $table->text('delivery_address_text')->nullable()->after('address_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['address_id', 'delivery_address_text']);
        });
    }
};
