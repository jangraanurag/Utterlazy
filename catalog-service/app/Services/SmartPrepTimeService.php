<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Cache;

class SmartPrepTimeService
{
    // Prep time in minutes per item category
    protected array $basePrepTimes = [
        'default' => 10,
        'biryani' => 20,
        'rice' => 15,
        'tandoori' => 18,
        'curry' => 12,
        'bread' => 8,
        'dessert' => 5,
        'beverage' => 3,
        'starter' => 12,
        'fried' => 15,
    ];

    /**
     * Predict prep time using ML-like formula based on order data.
     */
    public function predictPrepTime(Order $order): int
    {
        $order->load('items.menuItem');

        $totalQty = $order->items->sum('quantity');
        $items = $order->items;

        if ($totalQty === 0) {
            return 15;
        }

        // Base: 10 min + 2 min per item
        $estimated = 10 + ($totalQty * 2);

        // Category adjustments
        foreach ($items as $item) {
            $name = strtolower($item->menuItem?->name ?? '');
            $found = false;
            foreach ($this->basePrepTimes as $keyword => $time) {
                if (str_contains($name, $keyword)) {
                    $estimated += ($time - 10); // premium over base of 10
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                $estimated += 5; // unknown items need more time
            }
        }

        // Kitchen load factor (based on recent orders)
        $recentOrdersCount = Order::where('restaurant_id', $order->restaurant_id)
            ->whereIn('status', ['confirmed', 'preparing'])
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($recentOrdersCount > 5) {
            $estimated += 5;
        }
        if ($recentOrdersCount > 10) {
            $estimated += 5;
        }

        // Historical average (if available)
        $historicalAvg = $this->getHistoricalAvg($order->restaurant_id);
        if ($historicalAvg > 0) {
            $estimated = (int) round(($estimated * 0.6) + ($historicalAvg * 0.4));
        }

        return max(5, min($estimated, 45)); // Clamp between 5 and 45 min
    }

    /**
     * Get average historical prep time for this restaurant.
     */
    protected function getHistoricalAvg(string $restaurantId): int
    {
        $cacheKey = "prep_time_avg:{$restaurantId}";

        return Cache::remember($cacheKey, 3600, function () use ($restaurantId) {
            $avg = Order::where('restaurant_id', $restaurantId)
                ->whereNotNull('prep_time_minutes')
                ->where('created_at', '>=', now()->subDays(30))
                ->avg('prep_time_minutes');

            return (int) round($avg ?? 0);
        });
    }
}
