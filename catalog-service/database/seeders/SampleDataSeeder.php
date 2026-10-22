<?php

namespace Database\Seeders;

use App\Models\BlogPost;
use App\Models\CustomerInsight;
use App\Models\EvChargingStation;
use App\Models\FestivalCampaign;
use App\Models\Recipe;
use App\Models\Restaurant;
use App\Models\SurgePricingConfig;
use App\Models\User;
use Illuminate\Database\Seeder;

class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Festival Campaigns ───
        $festivals = [
            ['name' => 'Diwali Dhamaka', 'slug' => 'diwali-2026', 'start_date' => '2026-10-20', 'end_date' => '2026-11-05', 'description' => 'Festival of lights special! Extra discounts and sweets specials.', 'regions' => ['north', 'west', 'east'], 'config' => ['discount_percent' => 20, 'free_delivery' => true], 'is_active' => true],
            ['name' => 'Eid Celebrations', 'slug' => 'eid-2026', 'start_date' => '2026-04-01', 'end_date' => '2026-04-10', 'description' => 'Special biryani and kebab offers.', 'regions' => ['north', 'south'], 'config' => ['discount_percent' => 15], 'is_active' => true],
            ['name' => 'Pongal Special', 'slug' => 'pongal-2026', 'start_date' => '2026-01-13', 'end_date' => '2026-01-16', 'description' => 'Traditional Tamil feast offers.', 'regions' => ['south'], 'config' => ['discount_percent' => 25], 'is_active' => true],
            ['name' => 'Holi Hungama', 'slug' => 'holi-2026', 'start_date' => '2026-03-15', 'end_date' => '2026-03-20', 'description' => 'Colorful festival specials!', 'regions' => ['north', 'west', 'east'], 'config' => ['discount_percent' => 18], 'is_active' => true],
        ];
        foreach ($festivals as $f) {
            FestivalCampaign::create($f);
        }

        // ─── Blog Posts ───
        $posts = [
            ['title' => 'Top 10 Biryani Places in Bhiwani', 'slug' => 'top-10-biryani-bhiwani', 'excerpt' => 'Discover the best biryani joints in Bhiwani that will blow your mind.', 'content' => 'Bhiwani may be known for its boxers, but its biryani game is strong too! From the melt-in-mouth Awadhi dum biryani at Saffron Palace to the spicy Hyderabadi version at Delhi Darbar, we have rounded up the top 10 places...', 'tags' => ['biryani', 'bhiwani', 'food-guide'], 'author' => 'UtterLazy Foodie', 'status' => 'published', 'published_at' => now()],
            ['title' => 'How to Order Food on a Budget', 'slug' => 'budget-food-ordering-tips', 'excerpt' => 'Smart tips to save money while ordering food online.', 'content' => 'Ordering food doesn\'t have to break the bank. Use delivery passes, order during off-peak hours, look for combo offers, and check out our daily deals section to save big on every order...', 'tags' => ['budget', 'tips', 'savings'], 'author' => 'UtterLazy Team', 'status' => 'published', 'published_at' => now()],
            ['title' => 'Guide to Jain Food in Tier 2 Cities', 'slug' => 'jain-food-guide', 'excerpt' => 'Finding authentic Jain food just got easier with UtterLazy.', 'content' => 'Jain food is not just about skipping onions and garlic. It is a way of life. We have partnered with restaurants that understand the nuances of Jain cooking. Use the dietary filter on the app to find Jain-friendly options near you...', 'tags' => ['jain', 'dietary', 'guide'], 'author' => 'UtterLazy Foodie', 'status' => 'published', 'published_at' => now()],
        ];
        foreach ($posts as $p) {
            BlogPost::create($p);
        }

        // ─── Recipes ───
        $recipes = [
            ['title' => 'Classic Butter Chicken', 'description' => 'Creamy, rich, and absolutely delicious butter chicken that rivals any restaurant.', 'ingredients' => ['500g chicken thighs', '2 tbsp butter', '1 cup cream', '2 tbsp tomato paste', '1 tsp garam masala', '1 tsp turmeric', 'Salt to taste', 'Fresh coriander'], 'steps' => ['Marinate chicken with turmeric and salt for 30 min', 'Grill chicken until charred', 'Prepare gravy with butter, tomato paste, and cream', 'Add chicken to gravy, simmer for 15 min', 'Garnish with coriander and butter'], 'cuisine' => 'North Indian', 'prep_time_min' => 15, 'cook_time_min' => 35, 'difficulty' => 'medium'],
            ['title' => 'Veg Biryani', 'description' => 'Aromatic layered rice dish with mixed vegetables and fragrant spices.', 'ingredients' => ['2 cups basmati rice', '1 cup mixed vegetables', '2 onions sliced', '1 tbsp biryani masala', 'Saffron strands', 'Mint leaves', 'Ghee'], 'steps' => ['Parboil rice with whole spices', 'Fry onions until golden', 'Layer vegetables and rice', 'Add saffron milk and mint', 'Dum cook for 20 min'], 'cuisine' => 'Mughlai', 'prep_time_min' => 20, 'cook_time_min' => 40, 'difficulty' => 'hard'],
            ['title' => 'Quick Paneer Tikka', 'description' => 'Perfect starter for any occasion. Ready in 20 minutes!', 'ingredients' => ['200g paneer', '1 cup yogurt', '1 tsp chaat masala', '1 tsp ginger-garlic paste', '1 tbsp tandoori masala', 'Lemon juice', 'Onion rings', 'Capsicum'], 'steps' => ['Cut paneer into cubes', 'Mix yogurt with all spices', 'Marinate for 30 min', 'Grill or bake until charred', 'Serve with mint chutney'], 'cuisine' => 'North Indian', 'prep_time_min' => 10, 'cook_time_min' => 10, 'difficulty' => 'easy'],
        ];
        foreach ($recipes as $r) {
            Recipe::create($r);
        }

        // ─── EV Charging Stations ───
        $stations = [
            ['name' => 'Bhiwani EV Point', 'address' => 'Circular Road, Bhiwani', 'latitude' => 28.7942, 'longitude' => 76.1384, 'available_slots' => 3, 'is_active' => true],
            ['name' => 'Hansi Road Charger', 'address' => 'Hansi Road, Bhiwani', 'latitude' => 28.7980, 'longitude' => 76.1420, 'available_slots' => 5, 'is_active' => true],
            ['name' => 'Bus Stand Station', 'address' => 'Main Bus Stand, Bhiwani', 'latitude' => 28.7915, 'longitude' => 76.1330, 'available_slots' => 2, 'is_active' => true],
        ];
        foreach ($stations as $s) {
            EvChargingStation::create($s);
        }

        // ─── Surge Pricing Config ───
        SurgePricingConfig::create([
            'time_slots' => ['12-14' => 1.3, '19-22' => 1.5],
            'weather_multipliers' => ['rainy' => 1.2, 'extreme_heat' => 1.1],
            'demand_thresholds' => ['orders_per_rider_3' => 1.3, 'orders_per_rider_5' => 1.8],
            'is_active' => true,
        ]);

        // ─── Customer Insights (for existing restaurants) ───
        $users = User::take(5)->get();
        $restaurants = Restaurant::take(3)->get();
        foreach ($restaurants as $restaurant) {
            foreach ($users as $user) {
                CustomerInsight::create([
                    'restaurant_id' => $restaurant->id,
                    'user_id' => $user->id,
                    'order_count' => rand(2, 15),
                    'total_spent' => rand(500, 5000),
                    'last_order_at' => now()->subDays(rand(1, 30)),
                ]);
            }
        }

        $this->command->info('✅ Sample data seeded: '.count($festivals).' festivals, '.count($posts).' blog posts, '.count($recipes).' recipes, '.count($stations).' EV stations, surge config, customer insights');
    }
}
