<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Rider rates restaurant (at pickup)
            $table->unsignedTinyInteger('rider_rating_for_restaurant')->nullable()->after('handover_delay_minutes');
            $table->text('rider_feedback_for_restaurant')->nullable()->after('rider_rating_for_restaurant');

            // Restaurant rates rider (after pickup/handover)
            $table->unsignedTinyInteger('restaurant_rating_for_rider')->nullable()->after('rider_feedback_for_restaurant');
            $table->text('restaurant_feedback_for_rider')->nullable()->after('restaurant_rating_for_rider');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'rider_rating_for_restaurant',
                'rider_feedback_for_restaurant',
                'restaurant_rating_for_rider',
                'restaurant_feedback_for_rider',
            ]);
        });
    }
};
