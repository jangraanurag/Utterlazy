<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Favorite;
use App\Models\GiftCard;
use App\Models\MenuItem;
use App\Models\Offer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Referral;
use App\Models\Restaurant;
use App\Models\RestaurantAnalytics;
use App\Models\RestaurantRating;
use App\Models\Review;
use App\Models\Rider;
use App\Models\SponsoredListing;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ComprehensiveDummySeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🍕 Seeding comprehensive dummy data...');

        // ─── 1. CUSTOMERS ───
        $customers = [];
        $customerData = [
            ['name' => 'Anurag', 'email' => 'anurag@welo.com', 'phone' => '9999988888', 'wallet_balance' => 5000],
            ['name' => 'Priya Sharma', 'email' => 'priya@example.com', 'phone' => '9876543210', 'wallet_balance' => 2500],
            ['name' => 'Rahul Verma', 'email' => 'rahul@example.com', 'phone' => '9876543211', 'wallet_balance' => 1800],
            ['name' => 'Sneha Patel', 'email' => 'sneha@example.com', 'phone' => '9876543212', 'wallet_balance' => 3200],
            ['name' => 'Amit Singh', 'email' => 'amit@example.com', 'phone' => '9876543213', 'wallet_balance' => 900],
            ['name' => 'Neha Gupta', 'email' => 'neha@example.com', 'phone' => '9876543214', 'wallet_balance' => 4100],
            ['name' => 'Vikram Joshi', 'email' => 'vikram@example.com', 'phone' => '9876543215', 'wallet_balance' => 1500],
            ['name' => 'Pooja Reddy', 'email' => 'pooja@example.com', 'phone' => '9876543216', 'wallet_balance' => 2200],
            ['name' => 'Karan Malhotra', 'email' => 'karan@example.com', 'phone' => '9876543217', 'wallet_balance' => 3700],
            ['name' => 'Divya Nair', 'email' => 'divya@example.com', 'phone' => '9876543218', 'wallet_balance' => 1100],
        ];

        foreach ($customerData as $c) {
            $customers[] = User::updateOrCreate(
                ['email' => $c['email']],
                ['name' => $c['name'], 'password' => Hash::make('password123'), 'phone' => $c['phone'], 'wallet_balance' => $c['wallet_balance']]
            );
        }
        $this->command->info('  ✅ '.count($customers).' customers');

        // ─── 2. ADDRESSES ───
        $addressData = [
            ['label' => 'Home', 'flat_building' => '42, Sunshine Apartments', 'street' => 'Circular Road', 'landmark' => 'Near Nehru Park', 'city' => 'Bhiwani', 'state' => 'Haryana', 'pincode' => '127021', 'latitude' => 28.7942, 'longitude' => 76.1384, 'location_name' => 'Home'],
            ['label' => 'Office', 'flat_building' => '3rd Floor, Tech Park', 'street' => 'Hansi Road', 'landmark' => 'Opposite Bus Stand', 'city' => 'Bhiwani', 'state' => 'Haryana', 'pincode' => '127021', 'latitude' => 28.7980, 'longitude' => 76.1420, 'location_name' => 'Office'],
            ['label' => 'Home', 'flat_building' => '15, Green Valley Society', 'street' => 'Rohtak Road', 'landmark' => 'Near Clock Tower', 'city' => 'Bhiwani', 'state' => 'Haryana', 'pincode' => '127021', 'latitude' => 28.7915, 'longitude' => 76.1330, 'location_name' => 'Home'],
            ['label' => 'Hostel', 'flat_building' => 'Room 204, Krishna Hostel', 'street' => 'GT Road', 'landmark' => 'Near Engineering College', 'city' => 'Bhiwani', 'state' => 'Haryana', 'pincode' => '127021', 'latitude' => 28.7890, 'longitude' => 76.1400, 'location_name' => 'Hostel'],
            ['label' => 'Home', 'flat_building' => '78, Lakshmi Nagar', 'street' => 'Delhi Road', 'landmark' => 'Near SBI ATM', 'city' => 'Bhiwani', 'state' => 'Haryana', 'pincode' => '127021', 'latitude' => 28.8010, 'longitude' => 76.1350, 'location_name' => 'Home'],
        ];

        foreach ($customers as $i => $customer) {
            $addrIndex = $i % count($addressData);
            Address::create(array_merge($addressData[$addrIndex], ['user_id' => $customer->id, 'is_default' => true]));
            // Give some customers a second address
            if ($i % 2 === 0) {
                Address::create(array_merge($addressData[($addrIndex + 1) % count($addressData)], ['user_id' => $customer->id, 'is_default' => false]));
            }
        }
        $this->command->info('  ✅ Addresses seeded');

        // ─── 3. RESTAURANTS ───
        $restaurants = [];
        $restaurantData = [
            [
                'name' => 'The Saffron Palace', 'email' => 'vendor@utterlazy.com', 'phone' => '9896012345',
                'description' => 'Masters of slow-cooked Dum Biryani and delicate Galouti Kebabs. Authentic Awadhi royal dining.',
                'latitude' => 28.7942, 'longitude' => 76.1384,
                'address' => 'Circular Road, near Nehru Park, Bhiwani, Haryana 127021',
                'fssai_number' => '12345678901234', 'gst_number' => '06BBBBB2222B2Z2', 'pan_number' => 'BBBBB2222B',
                'avg_prep_time_minutes' => 25, 'total_orders_completed' => 1847, 'total_revenue' => 485000,
                'is_sponsored' => true, 'ad_budget' => 5000, 'ad_clicks' => 342,
            ],
            [
                'name' => 'Delhi Darbar', 'email' => 'delhi@utterlazy.com', 'phone' => '9896012346',
                'description' => 'Rich curries, tandoori specialties, and hot naans straight from the heart of Old Delhi.',
                'latitude' => 28.7980, 'longitude' => 76.1420,
                'address' => 'Hansi Road, opposite Main Bus Stand, Bhiwani, Haryana 127021',
                'fssai_number' => '98765432109876', 'gst_number' => '07AAAAA1111A1Z1', 'pan_number' => 'AAAAA1111A',
                'avg_prep_time_minutes' => 30, 'total_orders_completed' => 1203, 'total_revenue' => 312000,
            ],
            [
                'name' => 'The Burger Club', 'email' => 'burger@utterlazy.com', 'phone' => '9896012347',
                'description' => 'Juicy craft burgers, crispy fries, and premium thick milkshakes.',
                'latitude' => 28.7915, 'longitude' => 76.1330,
                'address' => 'Rohtak Road, near Clock Tower, Bhiwani, Haryana 127021',
                'fssai_number' => '11122233344455', 'pan_number' => 'CCCCC3333C',
                'avg_prep_time_minutes' => 15, 'total_orders_completed' => 2156, 'total_revenue' => 298000,
            ],
            [
                'name' => 'Spice Garden', 'email' => 'spice@utterlazy.com', 'phone' => '9896012349',
                'description' => 'Authentic South Indian flavors — dosas, idlis, and filter coffee.',
                'latitude' => 28.7890, 'longitude' => 76.1400,
                'address' => 'GT Road, Near Engineering College, Bhiwani, Haryana 127021',
                'fssai_number' => '22233344455566', 'pan_number' => 'EEEEE5555E',
                'avg_prep_time_minutes' => 20, 'total_orders_completed' => 978, 'total_revenue' => 178000,
            ],
            [
                'name' => 'Pizza Heaven', 'email' => 'pizza@utterlazy.com', 'phone' => '9896012350',
                'description' => 'Wood-fired pizzas, Italian pastas, and garlic breads. Pure veg.',
                'latitude' => 28.8010, 'longitude' => 76.1350,
                'address' => 'Delhi Road, Near SBI ATM, Bhiwani, Haryana 127021',
                'fssai_number' => '33344455566677', 'pan_number' => 'FFFFF6666F',
                'avg_prep_time_minutes' => 20, 'total_orders_completed' => 1564, 'total_revenue' => 423000,
                'is_sponsored' => true, 'ad_budget' => 3000, 'ad_clicks' => 218,
            ],
            [
                'name' => 'Chai & Chaat Corner', 'email' => 'chaat@utterlazy.com', 'phone' => '9896012351',
                'description' => 'Street food paradise — golgappe, tikki, samosa, and endless varieties of chai.',
                'latitude' => 28.7955, 'longitude' => 76.1395,
                'address' => 'Subhash Chowk, Bhiwani, Haryana 127021',
                'fssai_number' => '44455566677788', 'pan_number' => 'GGGGG7777G',
                'avg_prep_time_minutes' => 10, 'total_orders_completed' => 3210, 'total_revenue' => 245000,
            ],
            [
                'name' => 'Dragon Wok', 'email' => 'dragon@utterlazy.com', 'phone' => '9896012352',
                'description' => 'Sizzling Indo-Chinese — hakka noodles, manchurian, and schezwan specialties.',
                'latitude' => 28.7928, 'longitude' => 76.1370,
                'address' => 'Bazigar Chowk, Bhiwani, Haryana 127021',
                'fssai_number' => '55566677788899', 'pan_number' => 'HHHHH8888H',
                'avg_prep_time_minutes' => 18, 'total_orders_completed' => 867, 'total_revenue' => 156000,
            ],
            [
                'name' => 'Green Leaf Thali', 'email' => 'greenleaf@utterlazy.com', 'phone' => '9896012353',
                'description' => 'Unlimited thalis with homestyle Indian food. Budget-friendly and filling.',
                'latitude' => 28.7968, 'longitude' => 76.1410,
                'address' => 'Industrial Area, Bhiwani, Haryana 127021',
                'fssai_number' => '66677788899900', 'pan_number' => 'IIIII9999I',
                'avg_prep_time_minutes' => 15, 'total_orders_completed' => 2890, 'total_revenue' => 367000,
            ],
        ];

        foreach ($restaurantData as $r) {
            $restaurants[] = Restaurant::create(array_merge($r, [
                'password' => Hash::make('password123'),
                'is_active' => true,
                'is_accepting_orders' => true,
                'operating_hours' => ['open' => '10:00', 'close' => '23:00'],
            ]));
        }
        $this->command->info('  ✅ '.count($restaurants).' restaurants');

        // ─── 4. CATEGORIES & MENU ITEMS ───
        $menuImages = [
            'http://localhost:8001/images/dum_biryani.png',
            'http://localhost:8001/images/galouti_kebab.png',
            'http://localhost:8001/images/dal_makhani.png',
            'http://localhost:8001/images/burger_item_1777373706465.png',
            'http://localhost:8001/images/fries_item_1777373724412.png',
            'http://localhost:8001/images/bowl_item_1777373758378.png',
        ];

        $allMenuItems = [
            // Saffron Palace
            0 => [
                ['name' => 'Royal Awadhi Dum Biryani', 'description' => 'Aged basmati rice and tender mutton slow-cooked in a sealed copper handi with saffron, mace, and rose water.', 'price' => 249, 'category' => 'Biryani'],
                ['name' => 'Melt-in-Mouth Galouti Kebab', 'description' => 'Finely minced lamb infused with 150 rare spices, pan-fried on a griddle. Served on ulte tawa ka paratha.', 'price' => 199, 'category' => 'Starters'],
                ['name' => 'Signature Dal Makhani', 'description' => 'Black lentils simmered overnight on a slow charcoal fire, finished with fresh cream and churned butter.', 'price' => 179, 'category' => 'Main Course'],
                ['name' => 'Butter Naan', 'description' => 'Soft leavened bread baked in tandoor, brushed with clarified butter.', 'price' => 49, 'category' => 'Breads'],
                ['name' => 'Rasgulla', 'description' => 'Soft spongy cottage cheese balls soaked in light cardamom sugar syrup.', 'price' => 79, 'category' => 'Desserts'],
            ],
            // Delhi Darbar
            1 => [
                ['name' => 'Classic Butter Chicken', 'description' => 'Charcoal-grilled chicken tikka simmered in a velvety tomato-fenugreek gravy enriched with cream.', 'price' => 329, 'category' => 'Main Course'],
                ['name' => 'Truffle Garlic Naan', 'description' => 'Traditional leavened bread baked in a clay oven, infused with truffle oil and fresh garlic.', 'price' => 79, 'category' => 'Breads'],
                ['name' => 'Tandoori Chicken Full', 'description' => 'Whole chicken marinated in yogurt and spices, chargrilled in clay oven.', 'price' => 399, 'category' => 'Starters'],
                ['name' => 'Paneer Tikka Masala', 'description' => 'Grilled cottage cheese cubes in a rich, spiced tomato gravy.', 'price' => 269, 'category' => 'Main Course'],
            ],
            // Burger Club
            2 => [
                ['name' => 'Classic Smash Burger', 'description' => 'Double smashed patties with American cheese, pickles, onions, and special sauce.', 'price' => 179, 'category' => 'Burgers'],
                ['name' => 'Premium Veggie Burger', 'description' => 'Crispy mixed-veg patty with cheese, lettuce, and club sauce.', 'price' => 129, 'category' => 'Burgers'],
                ['name' => 'Golden Crispy Fries', 'description' => 'Lightly salted and perfectly crisped double-cooked potato fingers.', 'price' => 89, 'category' => 'Sides'],
                ['name' => 'Oreo Milkshake', 'description' => 'Thick and creamy milkshake blended with real Oreo cookies.', 'price' => 119, 'category' => 'Drinks'],
                ['name' => 'Loaded Cheese Fries', 'description' => 'Crispy fries topped with nacho cheese sauce and jalapeños.', 'price' => 149, 'category' => 'Sides'],
            ],
            // Spice Garden
            3 => [
                ['name' => 'Masala Dosa', 'description' => 'Crispy golden rice crepe filled with spiced potato masala. Served with sambar and chutney.', 'price' => 89, 'category' => 'Dosas'],
                ['name' => 'Idli Sambar', 'description' => 'Steamed rice cakes served with hot sambar and coconut chutney.', 'price' => 69, 'category' => 'Tiffin'],
                ['name' => 'Rava Dosa', 'description' => 'Crispy semolina crepe with onions, cashews, and curry leaves.', 'price' => 99, 'category' => 'Dosas'],
                ['name' => 'Filter Coffee', 'description' => 'Authentic South Indian filter coffee brewed with fresh ground beans.', 'price' => 39, 'category' => 'Drinks'],
                ['name' => 'Meals Plate', 'description' => 'Complete South Indian thali with rice, sambar, rasam, poriyal, curd, and papad.', 'price' => 149, 'category' => 'Meals'],
            ],
            // Pizza Heaven
            4 => [
                ['name' => 'Margherita Pizza', 'description' => 'Classic tomato sauce, fresh mozzarella, and basil on thin crust.', 'price' => 199, 'category' => 'Pizzas'],
                ['name' => 'Farmhouse Pizza', 'description' => 'Capsicum, mushrooms, onions, tomatoes, and olives on a cheese-loaded base.', 'price' => 299, 'category' => 'Pizzas'],
                ['name' => 'Penne Arrabiata', 'description' => 'Penne pasta in a spicy tangy tomato sauce with Italian herbs.', 'price' => 179, 'category' => 'Pasta'],
                ['name' => 'Garlic Breadsticks', 'description' => 'Warm breadsticks infused with garlic butter and herbs.', 'price' => 99, 'category' => 'Sides'],
                ['name' => 'Tiramisu', 'description' => 'Classic Italian dessert with mascarpone cream and coffee-soaked ladyfingers.', 'price' => 149, 'category' => 'Desserts'],
            ],
            // Chai & Chaat Corner
            5 => [
                ['name' => 'Samosa (2 pcs)', 'description' => 'Crispy golden pastries stuffed with spiced potatoes and peas.', 'price' => 39, 'category' => 'Street Food'],
                ['name' => 'Aloo Tikki Chaat', 'description' => 'Crispy potato patties topped with chutneys, yogurt, and pomegranate.', 'price' => 59, 'category' => 'Street Food'],
                ['name' => 'Golgappe (6 pcs)', 'description' => 'Hollow crispy puris filled with spicy water and tamarind chutney.', 'price' => 49, 'category' => 'Street Food'],
                ['name' => 'Masala Chai', 'description' => 'Strong tea brewed with ginger, cardamom, and cinnamon.', 'price' => 19, 'category' => 'Drinks'],
                ['name' => 'Lassi', 'description' => 'Thick and creamy yogurt drink blended with sugar.', 'price' => 49, 'category' => 'Drinks'],
            ],
            // Dragon Wok
            6 => [
                ['name' => 'Hakka Noodles', 'description' => 'Stir-fried egg noodles with vegetables and soy sauce.', 'price' => 119, 'category' => 'Noodles'],
                ['name' => 'Veg Manchurian Dry', 'description' => 'Crispy vegetable balls tossed in a tangy Indo-Chinese sauce.', 'price' => 149, 'category' => 'Starters'],
                ['name' => 'Schezwan Fried Rice', 'description' => 'Spicy fried rice with schezwan sauce and mixed vegetables.', 'price' => 129, 'category' => 'Rice'],
                ['name' => 'Chilli Paneer', 'description' => 'Cottage cheese cubes stir-fried with bell peppers and soy.', 'price' => 169, 'category' => 'Starters'],
            ],
            // Green Leaf Thali
            7 => [
                ['name' => 'Unlimited Veg Thali', 'description' => 'Complete meal with 4 sabzi, dal, rice, roti, salad, raita, and sweet.', 'price' => 149, 'category' => 'Thali'],
                ['name' => 'Paneer Thali', 'description' => 'Premium thali with paneer butter masala, dal makhani, rice, roti, and dessert.', 'price' => 199, 'category' => 'Thali'],
                ['name' => 'Dal Baati Churma', 'description' => 'Rajasthani specialty — baked wheat balls with panchmel dal and sweet churma.', 'price' => 179, 'category' => 'Special'],
                ['name' => 'Rajma Chawal', 'description' => 'Slow-cooked kidney bean curry served with steamed basmati rice.', 'price' => 119, 'category' => 'Rice'],
            ],
        ];

        foreach ($restaurants as $rIdx => $restaurant) {
            $items = $allMenuItems[$rIdx] ?? [];
            $catCache = [];
            foreach ($items as $item) {
                $catName = $item['category'];
                if (! isset($catCache[$catName])) {
                    $catCache[$catName] = Category::create([
                        'restaurant_id' => $restaurant->id,
                        'name' => $catName,
                        'sort_order' => count($catCache),
                    ]);
                }
                MenuItem::create([
                    'restaurant_id' => $restaurant->id,
                    'category_id' => $catCache[$catName]->id,
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'price' => $item['price'],
                    'image_url' => $menuImages[array_rand($menuImages)],
                    'is_available' => true,
                ]);
            }
        }
        $this->command->info('  ✅ Categories & menu items seeded');

        // ─── 5. RIDERS ───
        $riders = [];
        $riderData = [
            ['name' => 'Rajesh Kumar', 'email' => 'rider@utterlazy.com', 'phone' => '9876543210', 'vehicle_type' => 'bike', 'vehicle_number' => 'HR-16-AB-1234', 'total_deliveries' => 542, 'acceptance_rate' => 98.5, 'avg_rating' => 4.8],
            ['name' => 'Suresh Yadav', 'email' => 'suresh@rider.com', 'phone' => '9876543220', 'vehicle_type' => 'bike', 'vehicle_number' => 'HR-16-CD-5678', 'total_deliveries' => 321, 'acceptance_rate' => 95.2, 'avg_rating' => 4.6],
            ['name' => 'Manoj Kumar', 'email' => 'manoj@rider.com', 'phone' => '9876543221', 'vehicle_type' => 'bicycle', 'vehicle_number' => '', 'total_deliveries' => 178, 'acceptance_rate' => 92.0, 'avg_rating' => 4.5],
            ['name' => 'Amit Deshwal', 'email' => 'amit.d@rider.com', 'phone' => '9876543222', 'vehicle_type' => 'bike', 'vehicle_number' => 'HR-16-EF-9012', 'total_deliveries' => 89, 'acceptance_rate' => 88.5, 'avg_rating' => 4.3],
            ['name' => 'Deepak Siwach', 'email' => 'deepak@rider.com', 'phone' => '9876543223', 'vehicle_type' => 'scooter', 'vehicle_number' => 'HR-16-GH-3456', 'total_deliveries' => 412, 'acceptance_rate' => 97.1, 'avg_rating' => 4.9],
        ];

        foreach ($riderData as $r) {
            $riders[] = Rider::create(array_merge($r, [
                'password' => Hash::make('password123'),
                'latitude' => 28.7942 + (mt_rand(-50, 50) / 10000),
                'longitude' => 76.1384 + (mt_rand(-50, 50) / 10000),
                'is_online' => (bool) rand(0, 1),
                'is_verified' => true,
                'verification_status' => 'verified',
            ]));
        }
        $this->command->info('  ✅ '.count($riders).' riders');

        // ─── 6. ORDERS (past 30 days) ───
        $reviewTitles = [
            'Amazing food!', 'Loved it!', 'Great taste', 'Will order again', 'Best in town',
            'Decent food', 'Good portions', 'Quick delivery', 'Excellent quality', 'Worth the price',
            'A bit spicy', 'Average experience', 'Superb!', 'Highly recommend', 'Not bad',
        ];
        $reviewComments = [
            'The food was absolutely delicious. Packaging was also very good.',
            'Delivery was super quick. Hot and fresh food.',
            'Tasted like homemade food. Will definitely order again.',
            'Portions could be bigger but taste was excellent.',
            'One of the best restaurants on the platform. Keep it up!',
            'Good food, fair prices. What more do you need?',
            'The biryani was out of this world. Must try!',
            'Fresh ingredients and great presentation.',
            'Loved the variety on the menu. Something for everyone.',
            'Fast delivery and the food was still hot. Impressed!',
        ];
        $orderStatuses = ['delivered', 'delivered', 'delivered', 'delivered', 'cancelled'];
        $paymentModes = ['cod', 'prepaid', 'wallet'];
        $orderCount = 0;

        for ($day = 0; $day < 30; $day++) {
            $ordersPerDay = rand(2, 6);
            for ($o = 0; $o < $ordersPerDay; $o++) {
                $customer = $customers[array_rand($customers)];
                $restaurant = $restaurants[array_rand($restaurants)];
                $status = $orderStatuses[array_rand($orderStatuses)];
                $paymentMode = $paymentModes[array_rand($paymentModes)];

                $subtotal = rand(150, 800);
                $tax = round($subtotal * 0.05);
                $deliveryFee = rand(20, 60);
                $discount = ($subtotal > 300) ? rand(30, 100) : 0;
                $grandTotal = $subtotal + $tax + $deliveryFee - $discount;

                $orderDate = now()->subDays($day)->subHours(rand(8, 22))->subMinutes(rand(0, 59));

                $order = Order::create([
                    'user_id' => $customer->id,
                    'restaurant_id' => $restaurant->id,
                    'delivery_address_text' => 'Circular Road, Bhiwani, Haryana 127021',
                    'rider_id' => $riders[array_rand($riders)]->id,
                    'status' => $status,
                    'payment_mode' => $paymentMode,
                    'payment_status' => $status === 'delivered' ? 'paid' : ($status === 'cancelled' ? 'refunded' : 'pending'),
                    'subtotal' => $subtotal,
                    'tax_amount' => $tax,
                    'delivery_fee' => $deliveryFee,
                    'delivery_fee_customer' => $deliveryFee,
                    'vendor_discount_amount' => $discount,
                    'grand_total' => $grandTotal,
                    'delivery_distance_km' => round(mt_rand(10, 60) / 10, 1),
                    'created_at' => $orderDate,
                    'updated_at' => $orderDate,
                ]);

                if ($status === 'delivered') {
                    $order->update([
                        'delivered_at' => $orderDate->copy()->addMinutes(rand(20, 50)),
                    ]);
                }

                // Add 1-4 items per order
                $menuItems = MenuItem::where('restaurant_id', $restaurant->id)->get();
                if ($menuItems->isEmpty()) {
                    continue;
                }
                $itemsCount = rand(1, min(4, $menuItems->count()));
                $selectedItems = $menuItems->random($itemsCount);
                foreach ($selectedItems as $mi) {
                    $qty = rand(1, 2);
                    OrderItem::create([
                        'order_id' => $order->id,
                        'menu_item_id' => $mi->id,
                        'quantity' => $qty,
                        'unit_price' => $mi->price,
                        'total_price' => $mi->price * $qty,
                    ]);
                }

                // Create review for delivered orders
                if ($status === 'delivered' && rand(0, 1)) {
                    Review::create([
                        'user_id' => $customer->id,
                        'restaurant_id' => $restaurant->id,
                        'order_id' => $order->id,
                        'rating' => rand(3, 5),
                        'title' => $reviewTitles[array_rand($reviewTitles)],
                        'comment' => $reviewComments[array_rand($reviewComments)],
                        'food_rating' => rand(3, 5),
                        'delivery_rating' => rand(3, 5),
                        'packaging_rating' => rand(3, 5),
                        'helpful_count' => rand(0, 12),
                    ]);
                }
                $orderCount++;
            }
        }
        $this->command->info('  ✅ '.$orderCount.' orders with reviews');

        // ─── 7. COUPONS ───
        $couponData = [
            ['code' => 'WELCOME50', 'title' => 'WELCOME50', 'description' => 'Get flat ₹50 off on your first order.', 'type' => 'flat', 'value' => 50, 'min_order' => 199, 'per_user_limit' => 1],
            ['code' => 'UTTERLAZY60', 'title' => 'UTTERLAZY60', 'description' => 'Get 60% off up to ₹120 on your order.', 'type' => 'percentage', 'value' => 60, 'min_order' => 149, 'max_discount' => 120, 'per_user_limit' => 5],
            ['code' => 'FLAT100', 'title' => 'FLAT100', 'description' => 'Flat ₹100 off on orders above ₹499.', 'type' => 'flat', 'value' => 100, 'min_order' => 499, 'per_user_limit' => 2],
            ['code' => 'LUNCH30', 'title' => 'LUNCH30', 'description' => '30% off up to ₹80 on lunch orders.', 'type' => 'percentage', 'value' => 30, 'min_order' => 199, 'max_discount' => 80, 'per_user_limit' => 3],
            ['code' => 'FREESHIP', 'title' => 'FREESHIP', 'description' => 'Flat ₹40 off (free delivery) on all orders.', 'type' => 'flat', 'value' => 40, 'min_order' => 99, 'per_user_limit' => 10],
            ['code' => 'DIWALI200', 'title' => 'DIWALI200', 'description' => 'Get ₹200 off on festive orders above ₹799.', 'type' => 'flat', 'value' => 200, 'min_order' => 799, 'per_user_limit' => 1],
        ];

        foreach ($couponData as $c) {
            Coupon::create(array_merge($c, [
                'usage_limit' => null,
                'times_used' => rand(0, 50),
                'is_active' => true,
                'valid_from' => now()->subMonth(),
                'valid_until' => now()->addMonths(6),
            ]));
        }
        $this->command->info('  ✅ '.count($couponData).' coupons');

        // ─── 9. BANNERS ───
        $bannerData = [
            ['title' => 'Welcome to UtterLazy', 'description' => 'Too Lazy? Just Order.', 'image_url' => '/logo-large.png', 'link_type' => 'url', 'link_value' => '/', 'position' => 'home_top', 'is_active' => true, 'sort_order' => 1],
            ['title' => 'Flat 60% Off', 'description' => 'Use code UTTERLAZY60', 'image_url' => '/logo-large.png', 'link_type' => 'url', 'link_value' => '/', 'position' => 'home_top', 'is_active' => true, 'sort_order' => 2],
            ['title' => 'Free Delivery', 'description' => 'On orders above ₹99', 'image_url' => '/logo-large.png', 'link_type' => 'url', 'link_value' => '/', 'position' => 'home_middle', 'is_active' => true, 'sort_order' => 1],
        ];

        foreach ($bannerData as $b) {
            Banner::create($b);
        }
        $this->command->info('  ✅ '.count($bannerData).' banners');

        // ─── 10. OFFERS ───
        $offerData = [
            ['title' => 'Lunch Happy Hours', 'description' => 'Flat 20% off on orders between 12-3 PM', 'type' => 'percentage', 'discount_value' => 20, 'min_order_value' => 149, 'restaurant_id' => $restaurants[0]->id, 'applicable_type' => 'all'],
            ['title' => 'First Order Bonus', 'description' => '₹75 off on your first order from any restaurant', 'type' => 'flat', 'discount_value' => 75, 'min_order_value' => 199, 'restaurant_id' => $restaurants[0]->id, 'applicable_type' => 'all'],
        ];

        foreach ($offerData as $o) {
            Offer::create(array_merge($o, [
                'status' => 'active',
                'start_date' => now()->subWeek(),
                'end_date' => now()->addMonth(),
            ]));
        }
        $this->command->info('  ✅ '.count($offerData).' offers');

        // ─── 11. FAVORITES ───
        $favCount = 0;
        foreach ($customers as $customer) {
            $favs = collect($restaurants)->random(min(3, count($restaurants)));
            foreach ($favs as $fav) {
                Favorite::create(['user_id' => $customer->id, 'restaurant_id' => $fav->id]);
                $favCount++;
            }
        }
        $this->command->info('  ✅ '.$favCount.' favorites');

        // ─── 12. GIFT CARDS ───
        $giftCards = [
            ['code' => 'GIFT200-A1B2', 'initial_amount' => 200, 'remaining_amount' => 200, 'buyer_id' => $customers[0]->id],
            ['code' => 'GIFT500-C3D4', 'initial_amount' => 500, 'remaining_amount' => 500, 'buyer_id' => $customers[1]->id],
            ['code' => 'GIFT100-E5F6', 'initial_amount' => 100, 'remaining_amount' => 100, 'buyer_id' => $customers[2]->id],
        ];
        foreach ($giftCards as $gc) {
            GiftCard::create(array_merge($gc, [
                'expires_at' => now()->addYear(),
            ]));
        }
        $this->command->info('  ✅ '.count($giftCards).' gift cards');

        // ─── 13. REFERRALS ───
        foreach ($customers as $customer) {
            if (rand(0, 1)) {
                $referrer = $customers[array_rand($customers)];
                if ($referrer->id !== $customer->id) {
                    Referral::create([
                        'referrer_id' => $referrer->id,
                        'referred_id' => $customer->id,
                        'code' => strtoupper(substr(uniqid(), -6)),
                        'status' => 'completed',
                        'referrer_reward' => 50,
                        'referred_reward' => 50,
                        'completed_at' => now()->subDays(rand(1, 30)),
                    ]);
                }
            }
        }
        $this->command->info('  ✅ Referrals seeded');

        // ─── 14. RESTAURANT RATINGS ───
        $ratingCount = 0;
        foreach ($customers as $customer) {
            $ratedRestaurants = collect($restaurants)->random(min(3, count($restaurants)));
            foreach ($ratedRestaurants as $restaurant) {
                RestaurantRating::create([
                    'restaurant_id' => $restaurant->id,
                    'user_id' => $customer->id,
                    'rating' => rand(3, 5),
                    'feedback' => $reviewComments[array_rand($reviewComments)],
                ]);
                $ratingCount++;
            }
        }
        $this->command->info('  ✅ '.$ratingCount.' restaurant ratings');

        // ─── 15. SPONSORED LISTINGS ───
        foreach (array_slice($restaurants, 0, 2) as $restaurant) {
            SponsoredListing::create([
                'restaurant_id' => $restaurant->id,
                'daily_budget' => 500,
                'current_spend' => rand(100, 400),
                'status' => 'active',
                'start_date' => now()->subWeek()->toDateString(),
                'end_date' => now()->addMonth()->toDateString(),
                'bid_amount' => rand(10, 30),
                'ctr_estimate' => round(mt_rand(100, 500) / 10000, 4),
                'cvr_estimate' => round(mt_rand(10, 50) / 10000, 4),
            ]);
        }
        $this->command->info('  ✅ Sponsored listings');

        // ─── 16. RESTAURANT ANALYTICS ───
        foreach ($restaurants as $restaurant) {
            for ($d = 0; $d < 7; $d++) {
                RestaurantAnalytics::create([
                    'restaurant_id' => $restaurant->id,
                    'date' => now()->subDays($d)->toDateString(),
                    'total_orders' => rand(5, 30),
                    'total_revenue' => rand(2000, 15000),
                    'avg_order_value' => rand(150, 500),
                    'new_customers' => rand(0, 10),
                    'returning_customers' => rand(2, 15),
                    'avg_rating' => round(mt_rand(30, 50) / 10, 2),
                    'total_ratings' => rand(1, 10),
                ]);
            }
        }
        $this->command->info('  ✅ Restaurant analytics');

        $this->command->info('');
        $this->command->info('🎉 Comprehensive dummy data seeded successfully!');
        $this->command->info('   Customers: '.count($customers));
        $this->command->info('   Restaurants: '.count($restaurants));
        $this->command->info('   Riders: '.count($riders));
        $this->command->info("   Orders: {$orderCount}");
        $this->command->info('   Coupons: '.count($couponData));
    }
}
