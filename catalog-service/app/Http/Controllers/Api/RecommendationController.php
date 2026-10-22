<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Restaurant;
use App\Services\RankerClient;
use App\Services\RecommendationService;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    /**
     * Get personalized recommendations
     */
    public function index(Request $request)
    {
        $recommendations = RecommendationService::getPersonalizedRecommendations(
            $request->user()->id,
            $request->input('lat'),
            $request->input('lng')
        );

        return response()->json(['restaurants' => $recommendations]);
    }

    /**
     * Organic ranking (Wave 3). Sponsored placement never enters this path;
     * paid inventory lives in the ads auction and is labelled separately.
     *
     * Falls back to a deterministic local ranking when the ranker is disabled
     * or unreachable.
     */
    public function rankedOrganic(Request $request, RankerClient $ranker)
    {
        $validated = $request->validate([
            'lat' => 'sometimes|numeric|between:-90,90',
            'lng' => 'sometimes|numeric|between:-180,180',
            'limit' => 'sometimes|integer|min:1|max:50',
            'price_pref' => 'sometimes|in:any,low,medium,high',
            'assignment_key' => 'sometimes|string|max:120',
        ]);

        $limit = (int) ($validated['limit'] ?? 20);
        $lat = isset($validated['lat']) ? (float) $validated['lat'] : null;
        $lng = isset($validated['lng']) ? (float) $validated['lng'] : null;

        $restaurants = Restaurant::query()
            ->where('is_active', true)
            ->withCount('ratings')
            ->get(['id', 'name', 'logo_url', 'rating', 'latitude', 'longitude',
                'avg_prep_time_minutes', 'total_orders_completed', 'cuisine_type', 'price_for_two',
                'is_accepting_orders', 'city_id', 'is_sponsored']);

        $ids = $restaurants->pluck('id')->all();

        $weekOrders = Order::whereIn('restaurant_id', $ids)
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('restaurant_id, COUNT(*) AS c')
            ->groupBy('restaurant_id')
            ->pluck('c', 'restaurant_id');

        $cancelRates = Order::whereIn('restaurant_id', $ids)
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw("restaurant_id, COUNT(*) AS total, SUM(status = 'cancelled') AS cancelled")
            ->groupBy('restaurant_id')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->restaurant_id => ((int) $row->total) > 0
                    ? ((int) $row->cancelled) / ((int) $row->total)
                    : 0.0,
            ]);

        $candidates = $restaurants->map(function (Restaurant $r) use ($lat, $lng, $weekOrders, $cancelRates) {
            $distance = 0.0;

            if ($lat !== null && $lng !== null && $r->latitude && $r->longitude) {
                $distance = $this->haversine($lat, $lng, (float) $r->latitude, (float) $r->longitude);
            }

            return [
                'restaurant_id' => $r->id,
                'rating' => (float) ($r->rating ?? 0),
                'rating_count' => (int) ($r->ratings_count ?? 0),
                'distance_km' => round($distance, 3),
                'prep_time_minutes' => (float) ($r->avg_prep_time_minutes ?? 20),
                'order_count_7d' => (int) ($weekOrders[$r->id] ?? 0),
                'cancellation_rate' => round((float) ($cancelRates[$r->id] ?? 0), 4),
                'avg_price' => (float) ($r->price_for_two ?? 0),
                'cuisines' => $r->cuisine_type ? [(string) $r->cuisine_type] : [],
                'is_new' => ((int) ($r->total_orders_completed ?? 0)) < 20,
                'sponsored' => (bool) ($r->is_sponsored ?? false),
                'is_open' => (bool) $r->is_accepting_orders,
            ];
        })->values()->all();

        $ranked = $ranker->rank($candidates, [
            'radius_km' => 10,
            'limit' => $limit,
            'price_pref' => $validated['price_pref'] ?? 'any',
            'max_per_cuisine' => 2,
            'assignment_key' => $validated['assignment_key'] ?? null,
        ]);

        if ($ranked !== null) {
            $byId = $restaurants->keyBy('id');
            $items = collect($ranked['items'])
                ->map(fn ($item) => [
                    'restaurant' => $byId->get($item['restaurant_id']),
                    'score' => $item['score'] ?? null,
                    'breakdown' => $item['breakdown'] ?? null,
                    'sponsored' => (bool) ($item['sponsored'] ?? false),
                ])
                ->filter(fn ($item) => $item['restaurant'] !== null)
                ->values();

            return response()->json([
                'source' => 'ranker',
                'model_version' => $ranked['model_version'] ?? null,
                'scoring' => $ranked['scoring'] ?? 'policy',
                'experiment' => $ranked['experiment'] ?? null,
                'shadow' => $ranked['shadow'] ?? null,
                'items' => $items,
            ]);
        }

        $fallback = $restaurants
            ->sortByDesc(fn (Restaurant $r) => [$r->rating, $r->total_orders_completed])
            ->take($limit)
            ->map(fn (Restaurant $r) => [
                'restaurant' => $r,
                'score' => null,
                'breakdown' => null,
                'sponsored' => (bool) ($r->is_sponsored ?? false),
            ])
            ->values();

        return response()->json([
            'source' => 'php_fallback',
            'model_version' => null,
            'scoring' => 'php_fallback',
            'experiment' => null,
            'shadow' => null,
            'items' => $fallback,
        ]);
    }

    /**
     * Get order again suggestions
     */
    public function orderAgain(Request $request)
    {
        $suggestions = RecommendationService::getOrderAgainSuggestions($request->user()->id);

        return response()->json(['restaurants' => $suggestions]);
    }

    /**
     * Get trending restaurants
     */
    public function trending(Request $request)
    {
        $trending = RecommendationService::getTrendingRestaurants(
            $request->input('lat'),
            $request->input('lng')
        );

        return response()->json(['restaurants' => $trending]);
    }

    /**
     * Get popular dishes
     */
    public function popularDishes(Request $request)
    {
        $dishes = RecommendationService::getPopularDishes(
            $request->input('lat'),
            $request->input('lng')
        );

        return response()->json(['dishes' => $dishes]);
    }

    /**
     * Get "people also ordered" for a restaurant
     */
    public function peopleAlsoOrdered($restaurantId)
    {
        $restaurants = RecommendationService::getPeopleAlsoOrdered($restaurantId);

        return response()->json(['restaurants' => $restaurants]);
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
