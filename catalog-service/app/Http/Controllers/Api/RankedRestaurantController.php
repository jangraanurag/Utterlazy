<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Services\RestaurantRankingService;
use Illuminate\Http\Request;

class RankedRestaurantController extends Controller
{
    /**
     * Get ranked restaurant listing for the customer home screen.
     *
     * GET /api/restaurants/ranked
     * Query params: lat, lng, category, limit
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'lat' => 'sometimes|numeric|between:-90,90',
            'lng' => 'sometimes|numeric|between:-180,180',
            'category' => 'sometimes|string',
            'limit' => 'sometimes|integer|min:1|max:100',
            'gst_ready' => 'sometimes|boolean',
        ]);

        $user = $request->user();

        $ranked = RestaurantRankingService::getRankedRestaurants(
            userLat: $request->float('lat'),
            userLng: $request->float('lng'),
            userId: $user?->id,
            category: $request->input('category'),
            limit: (int) ($validated['limit'] ?? 50),
            gstReady: $request->boolean('gst_ready'),
        );

        return response()->json([
            'data' => array_map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'email' => $r->email,
                'phone' => $r->phone,
                'description' => $r->description,
                'logo_url' => $r->logo_url,
                'address' => $r->address,
                'is_active' => $r->is_active,
                'is_accepting_orders' => $r->is_accepting_orders,
                'latitude' => $r->latitude,
                'longitude' => $r->longitude,
                'rating' => $r->ema_consumer_rating,
                'cuisine_type' => $r->cuisine_type,
                'avg_prep_time_minutes' => $r->avg_prep_time_minutes,
                'total_orders_completed' => $r->total_orders_completed,
                'distance_km' => $r->distance_km ?? null,
                'ranking_score' => $r->_ranking_score,
                'sub_scores' => $r->_sub_scores ?? [],
            ], $ranked),
            'total' => count($ranked),
        ]);
    }

    /**
     * Get ranking sub-scores for a single restaurant (debug/detail).
     */
    public function show(Request $request, string $id)
    {
        $restaurant = Restaurant::find($id);
        if (! $restaurant) {
            return response()->json(['error' => 'Restaurant not found'], 404);
        }

        $user = $request->user();
        $ranked = RestaurantRankingService::getRankedRestaurants(
            userLat: $request->float('lat'),
            userLng: $request->float('lng'),
            userId: $user?->id,
            limit: 100,
        );

        $found = null;
        foreach ($ranked as $r) {
            if ($r->id === $id) {
                $found = $r;
                break;
            }
        }

        if (! $found) {
            return response()->json(['error' => 'Restaurant not in ranking results'], 404);
        }

        return response()->json([
            'data' => [
                'id' => $found->id,
                'name' => $found->name,
                'ranking_score' => $found->_ranking_score,
                'rank' => array_search($found, $ranked) + 1,
                'sub_scores' => $found->_sub_scores ?? [],
                'distance_km' => $found->distance_km ?? null,
            ],
        ]);
    }
}
