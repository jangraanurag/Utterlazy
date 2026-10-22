<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sponsored_listings', function (Blueprint $table) {
            $table->string('ad_type', 20)->default('ppc')->after('restaurant_id'); // ppc, card
            $table->string('placement', 50)->nullable()->after('ad_type'); // listings, search, both (null for card)
            $table->integer('duration_weeks')->default(1)->after('start_date');
            $table->decimal('weekly_budget', 10, 2)->default(0)->after('duration_weeks');
            $table->decimal('cpc', 10, 2)->default(0)->after('bid_amount'); // cost per click
            $table->string('target_customers', 20)->default('all')->after('cpc'); // all, new_users
            $table->time('time_from')->nullable()->after('target_customers');
            $table->time('time_to')->nullable()->after('time_from');
            $table->boolean('all_day')->default(true)->after('time_to');
            $table->timestamp('approved_at')->nullable()->after('end_date');
            $table->string('rejection_reason')->nullable()->after('approved_at');
            $table->string('created_by_type', 20)->nullable()->after('rejection_reason'); // restaurant, admin
            $table->string('created_by_id')->nullable()->after('created_by_type');
        });

        Schema::create('ad_performance_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('listing_id')->constrained('sponsored_listings')->cascadeOnDelete();
            $table->date('date');
            $table->integer('impressions')->default(0);
            $table->integer('clicks')->default(0);
            $table->integer('attributed_orders')->default(0);
            $table->decimal('spend', 10, 2)->default(0);
            $table->decimal('revenue', 10, 2)->default(0);
            $table->unique(['listing_id', 'date']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_performance_daily');
        Schema::table('sponsored_listings', function (Blueprint $table) {
            $table->dropColumn(['ad_type', 'placement', 'duration_weeks', 'weekly_budget', 'cpc', 'target_customers', 'time_from', 'time_to', 'all_day', 'approved_at', 'rejection_reason', 'created_by_type', 'created_by_id']);
        });
    }
};
