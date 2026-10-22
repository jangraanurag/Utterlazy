<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\RestaurantRating;
use App\Services\RestaurantRankingService;
use Illuminate\Http\Request;

class RatingController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'restaurant_id' => 'required|uuid',
            'order_id' => 'sometimes|uuid|nullable',
            'rating' => 'required|integer|min:1|max:5',
            'feedback' => 'sometimes|string|max:500',
        ]);

        $rating = RestaurantRating::updateOrCreate(
            [
                'restaurant_id' => $validated['restaurant_id'],
                'user_id' => $request->user()->id,
                'order_id' => $validated['order_id'] ?? null,
            ],
            [
                'rating' => $validated['rating'],
                'feedback' => $validated['feedback'] ?? null,
            ]
        );

        // Update restaurant average rating
        $avgRating = RestaurantRating::where('restaurant_id', $validated['restaurant_id'])->avg('rating');
        Restaurant::where('id', $validated['restaurant_id'])->update(['rating' => round($avgRating, 1)]);

        // Update EMA consumer rating (PDF §3.3)
        $restaurant = Restaurant::find($validated['restaurant_id']);
        if ($restaurant) {
            RestaurantRankingService::updateConsumerEma($restaurant, $validated['rating']);
        }

        return response()->json(['message' => 'Rating submitted', 'rating' => $rating]);
    }

    public function index(Request $request, $restaurantId)
    {
        $ratings = RestaurantRating::where('restaurant_id', $restaurantId)
            ->with('user:name')
            ->latest()
            ->paginate(20);

        return response()->json($ratings);
    }
}
