<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRestaurantRequest;
use App\Http\Resources\RestaurantResource;
use App\Models\GlobalCategory;
use App\Models\Restaurant;
use Illuminate\Http\Request;

class RestaurantController extends Controller
{
    /**
     * Display a listing of the resource.
     * Accepts ?lat=&lng=&radius= for geo-filtering (default 10km, max 10km).
     */
    public function index(Request $request)
    {
        $lat = $request->input('lat');
        $lng = $request->input('lng');
        $radius = min((float) $request->input('radius', 10), 10.0); // km
        $category = $request->input('category');

        $query = Restaurant::query();

        if ($lat && $lng) {
            // Haversine formula to calculate distance in km
            $query->selectRaw('
                *,
                (6371 * acos(
                    cos(radians(?)) * cos(radians(latitude))
                    * cos(radians(longitude) - radians(?))
                    + sin(radians(?)) * sin(radians(latitude))
                )) AS distance_km
            ', [$lat, $lng, $lat])
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->having('distance_km', '<=', $radius)
                ->orderBy('distance_km');
        }

        $query->where('is_active', true);

        if ($request->boolean('gst_ready')) {
            $query->whereNotNull('gst_number')->where('gst_number', '!=', '');
        }

        if ($category) {
            $query->whereHas('menuItems', function ($mq) use ($category) {
                $mq->where('is_available', true)
                    ->whereHas('globalCategory', function ($gq) use ($category) {
                        $gq->where('id', $category)
                            ->orWhere('name', $category);
                    });
            });
        }

        $restaurants = $query->get();

        return RestaurantResource::collection($restaurants);
    }

    /**
     * Get all active global categories.
     */
    public function globalCategories()
    {
        $categories = GlobalCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => $categories]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRestaurantRequest $request)
    {
        $restaurant = Restaurant::create($request->validated());

        return new RestaurantResource($restaurant);
    }

    /**
     * Display the specified resource.
     */
    public function show(Restaurant $restaurant)
    {
        return new RestaurantResource($restaurant);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
