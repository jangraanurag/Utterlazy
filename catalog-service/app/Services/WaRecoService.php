<?php

namespace App\Services;

use App\Models\Restaurant;
use App\Models\WaCityFloor;
use App\Models\WaPlacement;

class WaRecoService
{
    /**
     * Paid WhatsApp recommendation inventory for a city.
     *
     * Only active, in-window, budget-remaining placements whose bid meets the
     * pinned floor are eligible. Organic search never passes through here.
     */
    /**
     * Recommendation mix for WhatsApp: paid partners first (labelled), then
     * top organic restaurants so unpaid partners are never hidden.
     */
    public function recommendedInventory(string $cityId, int $limit = 5): array
    {
        $floorRow = WaCityFloor::find($cityId);
        $maxSlots = $floorRow?->max_slots ?? 5;
        $limit = max(1, min($limit, 20));

        $paid = $this->paidInventory($cityId, min($maxSlots, $limit));

        $remaining = $limit - count($paid);

        if ($remaining <= 0) {
            return $paid;
        }

        $exclude = array_column($paid, 'restaurant_id');

        $organic = Restaurant::where('city_id', $cityId)
            ->where('is_active', true)
            ->whereNotIn('id', $exclude)
            ->orderByDesc('rating')
            ->orderByDesc('total_orders_completed')
            ->limit($remaining)
            ->get(['id', 'name'])
            ->map(fn (Restaurant $r) => [
                'placement_id' => null,
                'restaurant_id' => $r->id,
                'restaurant_name' => $r->name,
                'title' => $r->name,
                'body' => '',
                'bid' => 0.0,
                'sponsored' => false,
            ])
            ->values()
            ->all();

        return array_merge($paid, $organic);
    }

    public function paidInventory(string $cityId, int $limit = 5): array
    {
        $floorRow = WaCityFloor::find($cityId);
        $maxSlots = $floorRow?->max_slots ?? 5;
        $limit = max(1, min($limit, $maxSlots));

        return WaPlacement::with('restaurant:id,name,logo_url,rating')
            ->where('city_id', $cityId)
            ->where('status', WaPlacement::STATUS_ACTIVE)
            ->where('start_at', '<=', now())
            ->where('end_at', '>', now())
            ->whereRaw('spent_today < daily_budget')
            ->whereRaw('cpc_bid >= COALESCE(pinned_floor, 0)')
            ->orderByDesc('cpc_bid')
            ->orderBy('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (WaPlacement $placement) => [
                'placement_id' => $placement->id,
                'restaurant_id' => $placement->restaurant_id,
                'restaurant_name' => $placement->restaurant?->name,
                'title' => $placement->creative_title,
                'body' => $placement->creative_body,
                'bid' => (float) $placement->cpc_bid,
                'sponsored' => true,
            ])
            ->values()
            ->all();
    }
}
