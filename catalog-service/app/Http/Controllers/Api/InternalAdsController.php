<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WaPlacement;
use App\Models\WaPlacementEvent;
use App\Services\OutboxPublisher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InternalAdsController extends Controller
{
    /**
     * Record an impression for one or more placements.
     * Impressions carry no spend; they exist for analytics and delivery pacing.
     */
    public function impression(Request $request, OutboxPublisher $publisher)
    {
        $validated = $request->validate([
            'placement_ids' => 'required|array|min:1|max:20',
            'placement_ids.*' => 'uuid',
            'external_id' => 'sometimes|string|max:80',
        ]);

        $logged = 0;

        foreach ($validated['placement_ids'] as $placementId) {
            $placement = WaPlacement::find($placementId);
            if (! $placement) {
                continue;
            }

            $inserted = DB::table('wa_placement_events')->insertOrIgnore([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'placement_id' => $placement->id,
                'city_id' => $placement->city_id,
                'event_type' => WaPlacementEvent::TYPE_IMPRESSION,
                'external_id' => $validated['external_id'] ?? null,
                'amount' => 0,
                'occurred_at' => now(),
                'created_at' => now(),
            ]);

            if ($inserted) {
                $publisher->enqueue('wa_placement', $placement->id, 'wa_placement.impression', [
                    'placement_id' => $placement->id,
                    'restaurant_id' => $placement->restaurant_id,
                    'city_id' => $placement->city_id,
                ], $placement->city_id);
                $logged++;
            }
        }

        return response()->json(['logged' => $logged]);
    }

    /**
     * Record a click and charge the placement's CPC bid once.
     * Idempotent on (placement, click, external_id). Auto-pauses on budget exhaustion.
     */
    public function click(Request $request, OutboxPublisher $publisher)
    {
        $validated = $request->validate([
            'placement_id' => 'required|uuid|exists:wa_placements,id',
            'external_id' => 'required|string|min:8|max:80',
        ]);

        $placement = WaPlacement::findOrFail($validated['placement_id']);

        if (! $placement->isRunnable()) {
            return response()->json(['error' => 'Placement is not runnable.', 'charged' => false], 422);
        }

        $charged = DB::transaction(function () use ($placement, $validated, $publisher) {
            $inserted = DB::table('wa_placement_events')->insertOrIgnore([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'placement_id' => $placement->id,
                'city_id' => $placement->city_id,
                'event_type' => WaPlacementEvent::TYPE_CLICK,
                'external_id' => $validated['external_id'],
                'amount' => (float) $placement->cpc_bid,
                'occurred_at' => now(),
                'created_at' => now(),
            ]);

            if (! $inserted) {
                return false;
            }

            $today = now()->toDateString();

            if ($placement->spent_date?->toDateString() !== $today) {
                $placement->spent_today = 0;
                $placement->spent_date = $today;
            }

            $placement->spent_today = (float) $placement->spent_today + (float) $placement->cpc_bid;

            if ($placement->spent_today >= (float) $placement->daily_budget) {
                $placement->status = WaPlacement::STATUS_EXHAUSTED;
            }

            $placement->save();

            $publisher->enqueue('wa_placement', $placement->id, 'wa_placement.click', [
                'placement_id' => $placement->id,
                'restaurant_id' => $placement->restaurant_id,
                'city_id' => $placement->city_id,
                'amount' => (float) $placement->cpc_bid,
                'spent_today' => (float) $placement->spent_today,
            ], $placement->city_id);

            return true;
        });

        return response()->json([
            'charged' => $charged,
            'spent_today' => (float) $placement->fresh()->spent_today,
            'status' => $placement->fresh()->status,
        ]);
    }
}
