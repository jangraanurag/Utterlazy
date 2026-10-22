<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->uuid('rider_id')->nullable()->after('restaurant_id');
            $table->timestamp('rider_assigned_at')->nullable()->after('rescue_discount_code');
            $table->timestamp('rider_picked_up_at')->nullable()->after('rider_assigned_at');
            $table->timestamp('delivered_at')->nullable()->after('rider_picked_up_at');
            $table->decimal('delivery_distance_km', 6, 2)->nullable()->after('delivered_at');
            $table->decimal('delivery_address_lat', 10, 7)->nullable()->after('delivery_distance_km');
            $table->decimal('delivery_address_lng', 10, 7)->nullable()->after('delivery_address_lat');
            $table->decimal('customer_tip', 8, 2)->default(0)->after('delivery_address_lng');

            $table->index('rider_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['rider_id']);
            $table->dropColumn([
                'rider_id', 'rider_assigned_at', 'rider_picked_up_at',
                'delivered_at', 'delivery_distance_km',
                'delivery_address_lat', 'delivery_address_lng', 'customer_tip',
            ]);
        });
    }
};
