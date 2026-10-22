<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\WaCityFloor;
use App\Models\WaPlacement;
use App\Services\AccountManagementApprovalBridge;
use App\Services\OutboxPublisher;
use Carbon\Carbon;
use Illuminate\Http\Request;

class WaPlacementController extends Controller
{
    public function index(Request $request)
    {
        $placements = WaPlacement::with('restaurant:id,name')
            ->where('restaurant_id', $request->user()->id)
            ->latest()
            ->paginate($request->get('per_page', 15));

        return response()->json($placements);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'city_id' => 'required|uuid|exists:cities,id',
            'daily_budget' => 'required|numeric|min:100|max:50000',
            'cpc_bid' => 'required|numeric|min:0.01',
            'start_at' => 'required|date',
            'end_at' => 'required|date|after:start_at',
            'creative_title' => 'required|string|min:3|max:40',
            'creative_body' => 'required|string|min:10|max:120',
        ]);

        $start = Carbon::parse($validated['start_at']);
        $end = Carbon::parse($validated['end_at']);

        if ($start->diffInDays($end) > 90) {
            return response()->json(['error' => 'Campaign span cannot exceed 90 days.'], 422);
        }

        $body = $validated['creative_body'];
        if (preg_match('/(\+?91)?[6-9]\d{9}/', preg_replace('/\s+/', '', $body))) {
            return response()->json(['error' => 'creative_body must not contain a phone number.'], 422);
        }
        if (stripos($body, 'guaranteed') !== false) {
            return response()->json(['error' => 'creative_body must not use the word "guaranteed".'], 422);
        }

        $city = City::find($validated['city_id']);
        $restaurant = $request->user();

        if ($city && $city->state && $restaurant->state && $city->state !== $restaurant->state) {
            return response()->json(['error' => 'The restaurant does not operate in the selected city.'], 422);
        }

        $floor = WaCityFloor::find($validated['city_id'])?->floor_cpc ?? 0.0;
        if ((float) $validated['cpc_bid'] < $floor) {
            return response()->json([
                'error' => 'CPC bid is below the city floor.',
                'floor_cpc' => $floor,
            ], 422);
        }

        $placement = WaPlacement::create([
            'restaurant_id' => $restaurant->id,
            'city_id' => $validated['city_id'],
            'daily_budget' => $validated['daily_budget'],
            'cpc_bid' => $validated['cpc_bid'],
            'start_at' => $start,
            'end_at' => $end,
            'creative_title' => $validated['creative_title'],
            'creative_body' => $validated['creative_body'],
            'status' => WaPlacement::STATUS_DRAFT,
        ]);

        $actor = \App\Models\User::where('phone', $restaurant->phone)
            ->orWhere('email', $restaurant->email)
            ->first();

        if ($actor) {
            AccountManagementApprovalBridge::originateWaPlacement($placement, $actor);
        } else {
            $placement->update(['status' => WaPlacement::STATUS_PENDING_REVIEW]);
        }

        return response()->json([
            'message' => 'WhatsApp placement submitted for approval.',
            'placement' => $placement->fresh(),
        ], 201);
    }

    public function pause(Request $request, WaPlacement $placement)
    {
        if ($placement->restaurant_id !== $request->user()->id) {
            return response()->json(['error' => 'Not your placement.'], 403);
        }

        if ($placement->status !== WaPlacement::STATUS_ACTIVE) {
            return response()->json(['error' => 'Only active placements can be paused.'], 422);
        }

        $placement->update(['status' => WaPlacement::STATUS_PAUSED]);

        app(OutboxPublisher::class)->enqueue(
            'wa_placement',
            $placement->id,
            'wa_placement.paused',
            [
                'placement_id' => $placement->id,
                'restaurant_id' => $placement->restaurant_id,
                'city_id' => $placement->city_id,
                'status' => $placement->status,
            ],
            $placement->city_id,
        );

        return response()->json(['message' => 'Placement paused.', 'placement' => $placement]);
    }

    public function adminIndex(Request $request)
    {
        $query = WaPlacement::with('restaurant:id,name')
            ->when($request->get('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest();

        return response()->json($query->paginate($request->get('per_page', 25)));
    }

    public function adminReject(Request $request, WaPlacement $placement)
    {
        $validated = $request->validate([
            'reason' => 'required|string|min:10|max:300',
        ]);

        if (! in_array($placement->status, [
            WaPlacement::STATUS_PENDING_REVIEW,
            WaPlacement::STATUS_DRAFT,
            WaPlacement::STATUS_ACTIVE,
        ])) {
            return response()->json(['error' => 'Placement cannot be rejected in its current state.'], 422);
        }

        $placement->update([
            'status' => WaPlacement::STATUS_REJECTED,
            'rejection_reason' => $validated['reason'],
        ]);

        app(OutboxPublisher::class)->enqueue(
            'wa_placement',
            $placement->id,
            'wa_placement.rejected',
            [
                'placement_id' => $placement->id,
                'restaurant_id' => $placement->restaurant_id,
                'city_id' => $placement->city_id,
                'status' => $placement->status,
                'rejection_reason' => $validated['reason'],
            ],
            $placement->city_id,
        );

        return response()->json(['message' => 'Placement rejected.', 'placement' => $placement]);
    }
}
