<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Restaurant;
use Illuminate\Http\Request;

class AdvancedSearchController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->input('q', '');
        $lat = $request->input('lat');
        $lng = $request->input('lng');
        $radius = min((float) $request->input('radius', 10), 10.0); // km, default 10km, capped at 10km
        $cuisine = $request->input('cuisine');
        $minRating = $request->input('min_rating');
        $maxPrice = $request->input('max_price');
        $minPrice = $request->input('min_price');
        $sortBy = $request->input('sort_by', 'relevance'); // relevance, rating, delivery_time, price_low, price_high
        $isVeg = $request->boolean('is_veg');
        $isOpen = $request->boolean('is_open');
        $freeDelivery = $request->boolean('free_delivery');
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 20);

        // Restaurant search with filters
        $restaurantQuery = Restaurant::query()
            ->where('is_active', true);

        // Text search
        if (strlen($query) >= 2) {
            $restaurantQuery->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                    ->orWhere('description', 'LIKE', "%{$query}%")
                    ->orWhere('cuisine_type', 'LIKE', "%{$query}%");
            });
        }

        // Cuisine filter
        if ($cuisine) {
            $cuisines = is_array($cuisine) ? $cuisine : explode(',', $cuisine);
            $restaurantQuery->whereIn('cuisine_type', $cuisines);
        }

        // Rating filter
        if ($minRating) {
            $restaurantQuery->where('rating', '>=', $minRating);
        }

        // Geo filter
        if ($lat && $lng) {
            $restaurantQuery->selectRaw('*, (
                6371 * acos(
                    cos(radians(?)) * cos(radians(latitude)) *
                    cos(radians(longitude) - radians(?)) +
                    sin(radians(?)) * sin(radians(latitude))
                )
            ) AS distance_km', [$lat, $lng, $lat])
                ->having('distance_km', '<=', $radius)
                ->orderBy('distance_km');
        }

        // Sorting
        switch ($sortBy) {
            case 'rating':
                $restaurantQuery->orderByDesc('rating');
                break;
            case 'delivery_time':
                $restaurantQuery->orderBy('delivery_time_minutes');
                break;
            case 'price_low':
                $restaurantQuery->orderBy('avg_price');
                break;
            case 'price_high':
                $restaurantQuery->orderByDesc('avg_price');
                break;
            default:
                if (! $lat || ! $lng) {
                    $restaurantQuery->orderByDesc('rating');
                }
        }

        $restaurants = $restaurantQuery->paginate($perPage);

        // Dish search
        $dishQuery = MenuItem::with('restaurant:id,name,delivery_time_minutes')
            ->where('is_available', true);

        if (strlen($query) >= 2) {
            $dishQuery->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                    ->orWhere('description', 'LIKE', "%{$query}%");
            });
        }

        if ($isVeg) {
            $dishQuery->where('is_veg', true);
        }

        if ($maxPrice) {
            $dishQuery->where('price', '<=', $maxPrice);
        }

        if ($minPrice) {
            $dishQuery->where('price', '>=', $minPrice);
        }

        $dishes = $dishQuery->limit(30)->get();

        // Popular searches
        $popularSearches = ['Biryani', 'Pizza', 'Burger', 'North Indian', 'Chinese', 'South Indian', 'Dosa', 'Butter Chicken'];

        return response()->json([
            'restaurants' => $restaurants,
            'dishes' => $dishes,
            'popular_searches' => $popularSearches,
            'filters_applied' => [
                'query' => $query,
                'cuisine' => $cuisine,
                'min_rating' => $minRating,
                'max_price' => $maxPrice,
                'is_veg' => $isVeg,
                'sort_by' => $sortBy,
            ],
        ]);
    }

    /**
     * Get autocomplete suggestions
     */
    public function autocomplete(Request $request)
    {
        $query = $request->input('q', '');

        if (strlen($query) < 2) {
            return response()->json(['suggestions' => []]);
        }

        $restaurantSuggestions = Restaurant::where('name', 'LIKE', "%{$query}%")
            ->limit(5)
            ->pluck('name')
            ->map(fn ($name) => ['text' => $name, 'type' => 'restaurant']);

        $dishSuggestions = MenuItem::where('name', 'LIKE', "%{$query}%")
            ->limit(5)
            ->pluck('name')
            ->map(fn ($name) => ['text' => $name, 'type' => 'dish']);

        return response()->json([
            'suggestions' => $restaurantSuggestions->concat($dishSuggestions)->take(8),
        ]);
    }

    /**
     * Get trending/popular restaurants
     */
    public function trending(Request $request)
    {
        $lat = $request->input('lat');
        $lng = $request->input('lng');

        $query = Restaurant::where('is_active', true)
            ->orderByDesc('rating')
            ->limit(10);

        if ($lat && $lng) {
            $query->selectRaw('*, (
                6371 * acos(
                    cos(radians(?)) * cos(radians(latitude)) *
                    cos(radians(longitude) - radians(?)) +
                    sin(radians(?)) * sin(radians(latitude))
                )
            ) AS distance_km', [$lat, $lng, $lat]);
        }

        return response()->json(['restaurants' => $query->get()]);
    }
}
