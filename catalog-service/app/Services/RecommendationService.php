<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use Illuminate\Support\Facades\Cache;

class RecommendationService
{
    /**
     * Get personalized restaurant recommendations for a user
     * Uses collaborative filtering + content-based hybrid approach
     */
    public static function getPersonalizedRecommendations($userId, $lat = null, $lng = null, $limit = 10)
    {
        $cacheKey = "recommendations:{$userId}";

        return Cache::remember($cacheKey, 300, function () use ($userId, $lat, $lng, $limit) {
            return self::computeRecommendations($userId, $lat, $lng, $limit);
        });
    }

    private static function computeRecommendations($userId, $lat, $lng, $limit)
    {
        // Get user's order history
        $userOrders = Order::where('user_id', $userId)
            ->with('restaurant')
            ->get();

        if ($userOrders->isEmpty()) {
            // Cold start: return top-rated restaurants
            return self::getTrendingRestaurants($lat, $lng, $limit);
        }

        // Extract user preferences from order history
        $cuisinePreferences = $userOrders->pluck('restaurant.cuisine_type')
            ->filter()->countBy()->sortDesc()->take(5)->keys()->toArray();

        $priceRange = $userOrders->avg('grand_total');
        $minPrice = max(0, $priceRange * 0.5);
        $maxPrice = $priceRange * 2;

        // Content-based: find similar restaurants
        $contentBased = Restaurant::where('is_active', true)
            ->whereIn('cuisine_type', $cuisinePreferences)
            ->whereNotIn('id', $userOrders->pluck('restaurant_id'))
            ->when($lat && $lng, function ($q) use ($lat, $lng) {
                $q->selectRaw('*, (
                    6371 * acos(
                        cos(radians(?)) * cos(radians(latitude)) *
                        cos(radians(longitude) - radians(?)) +
                        sin(radians(?)) * sin(radians(latitude))
                    )
                ) AS distance_km', [$lat, $lng, $lat]);
            })
            ->orderByDesc('rating')
            ->limit($limit * 2)
            ->get();

        // Collaborative: find what similar users ordered
        $similarUserIds = Order::whereIn('restaurant_id', $userOrders->pluck('restaurant_id'))
            ->where('user_id', '!=', $userId)
            ->pluck('user_id')
            ->unique()
            ->take(50);

        $collaborative = Order::whereIn('user_id', $similarUserIds)
            ->whereNotIn('restaurant_id', $userOrders->pluck('restaurant_id'))
            ->with('restaurant')
            ->get()
            ->pluck('restaurant')
            ->filter()
            ->unique('id')
            ->take($limit);

        // Merge and rank
        $merged = $contentBased->concat($collaborative)
            ->unique('id')
            ->take($limit);

        return $merged;
    }

    /**
     * Get trending/popular restaurants (for cold start)
     */
    public static function getTrendingRestaurants($lat = null, $lng = null, $limit = 10)
    {
        $cacheKey = 'trending_restaurants';

        return Cache::remember($cacheKey, 600, function () use ($limit) {
            return Restaurant::where('is_active', true)
                ->orderByDesc('rating')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Get "order again" suggestions based on past orders
     */
    public static function getOrderAgainSuggestions($userId, $limit = 10)
    {
        return Order::where('user_id', $userId)
            ->where('status', 'delivered')
            ->with('restaurant:id,name,logo_url,rating,delivery_time_minutes')
            ->with('items.menuItem:id,name,price,image_url')
            ->latest()
            ->limit($limit)
            ->get()
            ->pluck('restaurant')
            ->filter()
            ->unique('id');
    }

    /**
     * Get popular dishes in an area
     */
    public static function getPopularDishes($lat = null, $lng = null, $limit = 20)
    {
        return MenuItem::select('menu_items.*')
            ->join('orders', 'orders.restaurant_id', '=', 'menu_items.restaurant_id')
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.status', 'delivered')
            ->groupBy('menu_items.id')
            ->orderByRaw('SUM(order_items.quantity) DESC')
            ->limit($limit)
            ->get();
    }

    /**
     * Get "people also ordered" for a restaurant
     */
    public static function getPeopleAlsoOrdered($restaurantId, $limit = 10)
    {
        // Find restaurants that people who ordered from this restaurant also ordered from
        $userIds = Order::where('restaurant_id', $restaurantId)
            ->pluck('user_id')
            ->unique();

        return Order::whereIn('user_id', $userIds)
            ->where('restaurant_id', '!=', $restaurantId)
            ->with('restaurant:id,name,logo_url,rating')
            ->get()
            ->pluck('restaurant')
            ->filter()
            ->unique('id')
            ->take($limit);
    }
}
