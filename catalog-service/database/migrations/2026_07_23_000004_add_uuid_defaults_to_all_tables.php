<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tables that use char(36) UUID primary keys need DEFAULT UUID()
        $tables = [
            'addresses', 'banners', 'call_queues', 'cart_items', 'carts',
            'categories', 'cities', 'combo_offers', 'complaints',
            'coupon_usages', 'coupons', 'delivery_zones', 'favorites',
            'fcm_tokens', 'fraud_logs', 'gift_cards', 'global_categories',
            'group_order_items', 'group_orders', 'inventory_items', 'invoices',
            'memberships', 'menu_item_inventory', 'menu_items',
            'notification_campaigns', 'notification_preferences', 'offers',
            'order_items', 'orders', 'otps', 'pending_assignments',
            'referrals', 'restaurant_analytics', 'restaurant_applications',
            'restaurant_ratings', 'restaurant_staff', 'restaurants',
            'reviews', 'rider_assignments', 'rider_documents',
            'rider_payouts', 'rider_ratings', 'rider_training_lessons',
            'rider_training_modules', 'rider_training_quiz_questions',
            'riders', 'scheduled_orders', 'sponsored_listings',
            'system_configs', 'trip_stops', 'trips', 'user_searches',
            'users', 'vendor_payments', 'wallet_transactions',
        ];

        foreach ($tables as $table) {
            // MySQL-only: SQLite cannot use UUID() as a column default.
            if (DB::connection()->getDriverName() !== 'mysql') {
                continue;
            }
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'id')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->char('id', 36)->default(DB::raw('(UUID())'))->change();
                });
            }
        }
    }
};
