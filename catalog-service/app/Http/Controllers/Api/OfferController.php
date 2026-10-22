<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\Order;
use Illuminate\Http\Request;

class OfferController extends Controller
{
    public function index(Request $request)
    {
        $offers = Offer::where('restaurant_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['offers' => $offers]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:percentage,flat,bogo,free_delivery',
            'title' => 'required|string|max:255',
            'description' => 'sometimes|string',
            'discount_value' => 'required|numeric|min:0',
            'max_discount' => 'sometimes|numeric|min:0',
            'min_order_value' => 'sometimes|numeric|min:0',
            'applicable_type' => 'required|in:all,items,categories',
            'applicable_ids' => 'sometimes|array',
            'budget' => 'sometimes|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'schedule' => 'sometimes|array',
        ]);

        $validated['restaurant_id'] = $request->user()->id;
        $validated['status'] = 'active';

        $offer = Offer::create($validated);

        return response()->json(['message' => 'Offer created', 'offer' => $offer], 201);
    }

    public function update(Request $request, Offer $offer)
    {
        if ($offer->restaurant_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'type' => 'sometimes|in:percentage,flat,bogo,free_delivery',
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'discount_value' => 'sometimes|numeric|min:0',
            'max_discount' => 'sometimes|numeric|min:0',
            'min_order_value' => 'sometimes|numeric|min:0',
            'applicable_type' => 'sometimes|in:all,items,categories',
            'applicable_ids' => 'sometimes|array',
            'budget' => 'sometimes|numeric|min:0',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'schedule' => 'sometimes|array',
            'status' => 'sometimes|in:active,paused,ended',
        ]);

        $offer->update($validated);

        return response()->json(['message' => 'Offer updated', 'offer' => $offer->fresh()]);
    }

    public function destroy(Request $request, Offer $offer)
    {
        if ($offer->restaurant_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $offer->delete();

        return response()->json(['message' => 'Offer deleted']);
    }

    public function toggleStatus(Request $request, Offer $offer)
    {
        if ($offer->restaurant_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $newStatus = $offer->status === 'active' ? 'paused' : 'active';
        $offer->update(['status' => $newStatus]);

        return response()->json(['message' => "Offer $newStatus", 'status' => $newStatus]);
    }

    public function performance(Request $request, Offer $offer)
    {
        if ($offer->restaurant_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $orders = Order::where('restaurant_id', $request->user()->id)
            ->where('coupon_id', $offer->id)
            ->where('status', 'delivered')
            ->get();

        return response()->json([
            'total_orders' => $orders->count(),
            'total_discount' => $orders->sum('coupon_discount_amount'),
            'total_revenue' => $orders->sum('subtotal'),
            'used_count' => $offer->used_count,
            'budget' => $offer->budget,
            'remaining_budget' => max(0, $offer->budget - $orders->sum('coupon_discount_amount')),
        ]);
    }
}
