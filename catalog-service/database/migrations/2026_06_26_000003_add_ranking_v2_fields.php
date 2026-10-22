<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->decimal('ema_consumer_rating', 3, 2)->nullable()->after('is_active');
            $table->decimal('ema_rider_rating', 3, 2)->nullable()->after('ema_consumer_rating');
            $table->unsignedInteger('beta_alpha')->default(1)->after('ema_rider_rating');
            $table->unsignedInteger('beta_beta')->default(1)->after('beta_alpha');
            $table->decimal('batching_score', 3, 2)->default(0.50)->after('total_revenue');
            $table->decimal('prep_time_variance', 8, 2)->default(0)->after('batching_score');
            $table->unsignedInteger('total_impressions')->default(0)->after('prep_time_variance');
            $table->unsignedInteger('total_clicks')->default(0)->after('total_impressions');
        });

        Schema::table('sponsored_listings', function (Blueprint $table) {
            $table->decimal('bid_amount', 8, 2)->default(0)->after('priority');
            $table->decimal('target_roas', 5, 2)->nullable()->after('bid_amount');
            $table->decimal('ctr_estimate', 5, 4)->default(0)->after('target_roas');
            $table->decimal('cvr_estimate', 5, 4)->default(0)->after('ctr_estimate');
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn([
                'ema_consumer_rating', 'ema_rider_rating',
                'beta_alpha', 'beta_beta',
                'batching_score', 'prep_time_variance',
                'total_impressions', 'total_clicks',
            ]);
        });

        Schema::table('sponsored_listings', function (Blueprint $table) {
            $table->dropColumn(['bid_amount', 'target_roas', 'ctr_estimate', 'cvr_estimate']);
        });
    }
};
