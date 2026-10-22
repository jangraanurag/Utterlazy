<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdPerformanceDaily;
use App\Models\SponsoredListing;
use App\Services\AdRecommendationService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SponsoredListingController extends Controller
{
    public function index(Request $request)
    {
        $restaurantId = $request->user()->id;
        $query = SponsoredListing::where('restaurant_id', $restaurantId);

        if ($request->status && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->outlet_id) {
            $query->where('restaurant_id', $request->outlet_id);
        }

        $ads = $query->orderBy('created_at', 'desc')->get();

        return response()->json(['ads' => $ads]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'ad_type' => 'required|in:ppc,card',
            'placement' => 'required_if:ad_type,ppc|nullable|in:listings,search,both',
            'daily_budget' => 'required|numeric|min:1',
            'weekly_budget' => 'required|numeric|min:1',
            'cpc' => 'required|numeric|min:0.01',
            'start_date' => 'required|date',
            'duration_weeks' => 'required|integer|min:1|max:8',
            'end_date' => 'required|date|after_or_equal:start_date',
            'priority' => 'sometimes|integer|min:1|max:10',
            'target_customers' => 'sometimes|in:all,new_users',
            'time_from' => 'nullable|date_format:H:i',
            'time_to' => 'nullable|date_format:H:i',
            'all_day' => 'sometimes|boolean',
            'bid_amount' => 'sometimes|numeric|min:0',
        ]);

        $validated['restaurant_id'] = $request->user()->id;
        $validated['current_spend'] = 0;

        // Auto-set status: scheduled if future start, active if today
        $startDate = Carbon::parse($validated['start_date']);
        $validated['status'] = $startDate->isToday() || $startDate->isPast() ? 'active' : 'scheduled';

        $validated['created_by_type'] = 'restaurant';
        $validated['created_by_id'] = $request->user()->id;

        $listing = SponsoredListing::create($validated);

        return response()->json(['message' => 'Ad campaign created', 'ad' => $listing], 201);
    }

    public function update(Request $request, SponsoredListing $listing)
    {
        if ($listing->restaurant_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'daily_budget' => 'sometimes|numeric|min:1',
            'weekly_budget' => 'sometimes|numeric|min:1',
            'cpc' => 'sometimes|numeric|min:0.01',
            'start_date' => 'sometimes|date',
            'duration_weeks' => 'sometimes|integer|min:1|max:8',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'priority' => 'sometimes|integer|min:1|max:10',
            'target_customers' => 'sometimes|in:all,new_users',
            'time_from' => 'nullable|date_format:H:i',
            'time_to' => 'nullable|date_format:H:i',
            'all_day' => 'sometimes|boolean',
            'bid_amount' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|in:active,paused,scheduled',
        ]);

        $listing->update($validated);

        return response()->json(['message' => 'Ad campaign updated', 'ad' => $listing->fresh()]);
    }

    public function destroy(Request $request, SponsoredListing $listing)
    {
        if ($listing->restaurant_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $listing->delete();

        return response()->json(['message' => 'Ad campaign deleted']);
    }

    public function toggle(Request $request, SponsoredListing $listing)
    {
        if ($listing->restaurant_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $newStatus = $listing->status === 'active' ? 'paused' : 'active';
        $listing->update(['status' => $newStatus]);

        return response()->json(['message' => "Campaign {$newStatus}", 'status' => $newStatus]);
    }

    public function performance(Request $request, SponsoredListing $listing)
    {
        if ($listing->restaurant_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $daily = AdPerformanceDaily::where('listing_id', $listing->id)
            ->orderBy('date', 'asc')
            ->get();

        $totals = (object) [
            'impressions' => $daily->sum('impressions'),
            'clicks' => $daily->sum('clicks'),
            'attributed_orders' => $daily->sum('attributed_orders'),
            'spend' => $daily->sum('spend'),
            'revenue' => $daily->sum('revenue'),
        ];

        return response()->json([
            'daily' => $daily,
            'totals' => $totals,
            'ctr' => $totals->impressions > 0 ? round($totals->clicks / $totals->impressions * 100, 2) : 0,
            'conversion_rate' => $totals->clicks > 0 ? round($totals->attributed_orders / $totals->clicks * 100, 2) : 0,
            'cpo' => $totals->attributed_orders > 0 ? round($totals->spend / $totals->attributed_orders, 2) : 0,
            'roas' => $totals->spend > 0 ? round($totals->revenue / $totals->spend, 2) : 0,
        ]);
    }

    public function recommendations(Request $request, AdRecommendationService $service)
    {
        $request->validate([
            'ad_type' => 'required|in:ppc,card',
            'placement' => 'nullable|in:listings,search,both',
            'target_customers' => 'sometimes|in:all,new_users',
        ]);

        $restaurant = $request->user();

        $result = $service->getRecommendations(
            $request->ad_type,
            $request->placement,
            $request->target_customers ?? 'all',
            $restaurant->id,
            $request->boolean('all_day', true),
            $request->time_from,
            $request->time_to,
        );

        return response()->json($result);
    }

    // ─── Admin Endpoints ───

    public function adminIndex(Request $request)
    {
        $query = SponsoredListing::with('restaurant');

        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->ad_type) {
            $query->where('ad_type', $request->ad_type);
        }
        if ($request->restaurant_id) {
            $query->where('restaurant_id', $request->restaurant_id);
        }

        return response()->json(['ads' => $query->orderBy('created_at', 'desc')->paginate(20)]);
    }

    public function adminApprove(Request $request, SponsoredListing $listing)
    {
        if ($request->approve === false) {
            $request->validate(['rejection_reason' => 'required|string|max:500']);
            $listing->update([
                'status' => 'paused',
                'rejection_reason' => $request->rejection_reason,
            ]);

            return response()->json(['message' => 'Ad rejected', 'ad' => $listing->fresh()]);
        }

        $listing->update([
            'status' => 'active',
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        return response()->json(['message' => 'Ad approved', 'ad' => $listing->fresh()]);
    }

    public function adminUpdate(Request $request, SponsoredListing $listing)
    {
        $validated = $request->validate([
            'status' => 'sometimes|in:active,paused,scheduled,completed',
            'daily_budget' => 'sometimes|numeric|min:1',
            'cpc' => 'sometimes|numeric|min:0.01',
            'priority' => 'sometimes|integer|min:1|max:10',
            'end_date' => 'sometimes|date',
        ]);

        $listing->update($validated);

        return response()->json(['message' => 'Ad updated by admin', 'ad' => $listing->fresh()]);
    }
}
