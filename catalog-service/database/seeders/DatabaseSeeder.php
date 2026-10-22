<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        \Schema::disableForeignKeyConstraints();
        \DB::table('order_items')->truncate();
        \DB::table('orders')->truncate();
        \DB::table('reviews')->truncate();
        \DB::table('favorites')->truncate();
        \DB::table('addresses')->truncate();
        \DB::table('menu_items')->truncate();
        \DB::table('categories')->truncate();
        \DB::table('restaurant_ratings')->truncate();
        \DB::table('restaurant_analytics')->truncate();
        \DB::table('sponsored_listings')->truncate();
        \DB::table('gift_cards')->truncate();
        \DB::table('referrals')->truncate();
        \DB::table('coupons')->truncate();
        \DB::table('offers')->truncate();
        \DB::table('banners')->truncate();
        \DB::table('riders')->truncate();
        \DB::table('restaurants')->truncate();
        \DB::table('users')->truncate();
        \Schema::enableForeignKeyConstraints();

        $this->call(ComprehensiveDummySeeder::class);
        $this->call(RiderTrainingSeeder::class);
    }
}
