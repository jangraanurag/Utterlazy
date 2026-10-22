<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OptInCampaign;
use App\Models\RestaurantCampaignOptIn;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    // ─── Admin Endpoints ───

    public function adminIndex()
    {
        return response()->json(['campaigns' => OptInCampaign::withCount('restaurantOptIns')->orderBy('created_at', 'desc')->get()]);
    }

    public function adminStore(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'discount_type' => 'required|in:flat,percentage',
            'discount_value' => 'required|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'min_order_value' => 'sometimes|numeric|min:0',
            'share_platform' => 'required|numeric|min:0|max:100',
            'share_restaurant' => 'required|numeric|min:0|max:100',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        if (round($validated['share_platform'] + $validated['share_restaurant']) != 100) {
            return response()->json(['error' => 'Platform and restaurant shares must sum to 100%.'], 422);
        }

        $campaign = OptInCampaign::create($validated);

        return response()->json(['message' => 'Campaign created', 'campaign' => $campaign], 201);
    }

    public function adminUpdate(Request $request, OptInCampaign $campaign)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'discount_type' => 'sometimes|in:flat,percentage',
            'discount_value' => 'sometimes|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'min_order_value' => 'sometimes|numeric|min:0',
            'share_platform' => 'sometimes|numeric|min:0|max:100',
            'share_restaurant' => 'sometimes|numeric|min:0|max:100',
            'start_date' => 'sometimes|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'sometimes|boolean',
        ]);

        if (isset($validated['share_platform']) && isset($validated['share_restaurant'])) {
            if (round($validated['share_platform'] + $validated['share_restaurant']) != 100) {
                return response()->json(['error' => 'Platform and restaurant shares must sum to 100%.'], 422);
            }
        }

        $campaign->update($validated);

        return response()->json(['message' => 'Campaign updated', 'campaign' => $campaign->fresh()]);
    }

    // ─── Vendor Endpoints ───

    public function vendorAvailable(Request $request)
    {
        $restaurantId = $request->user()->id;
        $campaigns = OptInCampaign::where('is_active', true)
            ->where('start_date', '<=', now())
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            })
            ->get()
            ->map(function ($c) use ($restaurantId) {
                $optIn = RestaurantCampaignOptIn::where('campaign_id', $c->id)
                    ->where('restaurant_id', $restaurantId)
                    ->first();

                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'description' => $c->description,
                    'discount_type' => $c->discount_type,
                    'discount_value' => $c->discount_value,
                    'max_discount' => $c->max_discount,
                    'min_order_value' => $c->min_order_value,
                    'share_platform' => $c->share_platform,
                    'share_restaurant' => $c->share_restaurant,
                    'start_date' => $c->start_date,
                    'end_date' => $c->end_date,
                    'opted_in' => $optIn ? (bool) $optIn->opted_in : false,
                ];
            });

        return response()->json(['campaigns' => $campaigns]);
    }

    public function vendorOptIn(Request $request, OptInCampaign $campaign)
    {
        $restaurantId = $request->user()->id;

        $optIn = RestaurantCampaignOptIn::updateOrCreate(
            ['campaign_id' => $campaign->id, 'restaurant_id' => $restaurantId],
            ['opted_in' => true, 'opted_in_at' => now(), 'opted_out_at' => null]
        );

        return response()->json(['message' => 'Opted in to campaign', 'opted_in' => true]);
    }

    public function vendorOptOut(Request $request, OptInCampaign $campaign)
    {
        $restaurantId = $request->user()->id;

        RestaurantCampaignOptIn::updateOrCreate(
            ['campaign_id' => $campaign->id, 'restaurant_id' => $restaurantId],
            ['opted_in' => false, 'opted_out_at' => now()]
        );

        return response()->json(['message' => 'Opted out of campaign', 'opted_in' => false]);
    }

    // ─── Customer Endpoint ───

    public function activeForRestaurant(string $restaurantId)
    {
        $campaigns = OptInCampaign::where('is_active', true)
            ->where('start_date', '<=', now())
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            })
            ->get()
            ->filter(fn ($c) => $c->isRestaurantOptedIn($restaurantId))
            ->values()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'description' => $c->description,
                'discount_type' => $c->discount_type,
                'discount_value' => $c->discount_value,
                'max_discount' => $c->max_discount,
                'min_order_value' => $c->min_order_value,
            ]);

        return response()->json(['campaigns' => $campaigns]);
    }
}
