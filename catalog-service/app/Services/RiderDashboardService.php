<?php

namespace App\Services;

use App\Models\DailyRiderMetric;
use App\Models\Order;
use App\Models\RiderPayout;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RiderDashboardService
{
    /**
     * Get dashboard data for a rider over a period with comparison.
     */
    public static function getDashboard(string $riderId, string $period, ?string $startDate = null, ?string $endDate = null): array
    {
        [$periodStart, $periodEnd, $prevStart, $prevEnd] = self::resolvePeriod($period, $startDate, $endDate);

        $current = self::aggregate($riderId, $periodStart, $periodEnd);
        $previous = self::aggregate($riderId, $prevStart, $prevEnd);

        // Top 5 restaurants
        $topRestaurants = self::getTopRestaurants($riderId, $periodStart, $periodEnd);

        // Time slot analysis
        $timeSlots = self::getTimeSlotAnalysis($riderId, $periodStart, $periodEnd);

        return [
            'current_period' => [
                'start' => $periodStart->toDateString(),
                'end' => $periodEnd->toDateString(),
                'completed_journeys' => $current['completed'],
                'earnings' => round($current['earnings'], 2),
                'total_distance_km' => round($current['distance'], 2),
                'total_online_minutes' => $current['online_minutes'],
            ],
            'comparison' => [
                'start' => $prevStart->toDateString(),
                'end' => $prevEnd->toDateString(),
                'completed_journeys' => $previous['completed'],
                'earnings' => round($previous['earnings'], 2),
                'total_distance_km' => round($previous['distance'], 2),
                'completed_delta_pct' => self::delta($current['completed'], $previous['completed']),
                'earnings_delta_pct' => self::delta($current['earnings'], $previous['earnings']),
                'distance_delta_pct' => self::delta($current['distance'], $previous['distance']),
            ],
            'top_restaurants' => $topRestaurants,
            'time_slot_analysis' => $timeSlots,
        ];
    }

    /**
     * Get order history with status timeline and pay info.
     */
    public static function getHistory(string $riderId, int $page = 1, int $perPage = 20): array
    {
        $orders = Order::where('rider_id', $riderId)
            ->with(['restaurant:id,name'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        $payouts = RiderPayout::where('rider_id', $riderId)
            ->whereIn('order_id', $orders->pluck('id'))
            ->get()
            ->keyBy('order_id');

        $data = $orders->map(function ($order) use ($payouts) {
            $payout = $payouts->get($order->id);
            return [
                'order_id' => $order->id,
                'restaurant_name' => $order->restaurant?->name,
                'status' => $order->status,
                'created_at' => $order->created_at->setTimezone('Asia/Kolkata')->toIso8601String(),
                'distance_km' => (float) ($order->delivery_distance_km ?? 0),
                'pay' => $payout ? [
                    'distance_pay' => (float) $payout->distance_pay,
                    'base_pay' => (float) $payout->base_pay,
                    'surge_pay' => (float) $payout->surge_pay,
                    'wait_pay' => (float) $payout->wait_pay,
                    'tip' => (float) $payout->tip_amount,
                    'incentive' => (float) $payout->incentive_bonus,
                    'penalty' => (float) $payout->penalty,
                    'total' => (float) $payout->total_payout,
                ] : null,
                'is_paid' => $payout ? (bool) $payout->is_settled : false,
                'status_events' => collect($order->timeline)->map(function ($event) {
                    return [
                        'status' => $event['step'],
                        'label' => $event['label'],
                        'timestamp' => $event['time'] ? Carbon::parse($event['time'])
                            ->setTimezone('Asia/Kolkata')->toIso8601String() : null,
                        'completed' => $event['completed'],
                    ];
                })->values()->all(),
            ];
        });

        return [
            'data' => $data,
            'current_page' => $orders->currentPage(),
            'last_page' => $orders->lastPage(),
            'total' => $orders->total(),
        ];
    }

    /**
     * Generate or update daily metrics for a rider on a given date.
     */
    public static function aggregateDay(string $riderId, string $date): DailyRiderMetric
    {
        $dayStart = Carbon::parse($date)->startOfDay();
        $dayEnd = Carbon::parse($date)->endOfDay();

        $orders = Order::where('rider_id', $riderId)
            ->whereBetween('created_at', [$dayStart, $dayEnd])
            ->get();

        $completed = $orders->whereIn('status', ['delivered', 'completed'])->values();
        $cancelled = $orders->whereIn('status', ['cancelled', 'abandoned'])->values();

        $totalEarnings = RiderPayout::where('rider_id', $riderId)
            ->whereBetween('created_at', [$dayStart, $dayEnd])
            ->sum('total_payout');

        $totalDistance = $orders->sum('delivery_distance_km');

        // Restaurant breakdown
        $restaurantBreakdown = $completed->groupBy('restaurant_id')
            ->map(fn($items) => $items->count())
            ->toArray();

        // Time slot breakdown: 0-3, 3-6, 6-9, 9-12, 12-15, 15-18, 18-21, 21-24
        $slots = ['0-3' => 0, '3-6' => 0, '6-9' => 0, '9-12' => 0, '12-15' => 0, '15-18' => 0, '18-21' => 0, '21-24' => 0];
        foreach ($completed as $order) {
            $hour = $order->created_at->hour;
            if ($hour < 3) $slots['0-3']++;
            elseif ($hour < 6) $slots['3-6']++;
            elseif ($hour < 9) $slots['6-9']++;
            elseif ($hour < 12) $slots['9-12']++;
            elseif ($hour < 15) $slots['12-15']++;
            elseif ($hour < 18) $slots['15-18']++;
            elseif ($hour < 21) $slots['18-21']++;
            else $slots['21-24']++;
        }

        // Hourly earnings
        $hourlyEarnings = [];
        foreach (range(0, 23) as $h) {
            $hourlyEarnings[(string) $h] = 0;
        }
        foreach ($completed as $order) {
            $hour = (string) $order->created_at->hour;
            $hourlyEarnings[$hour] = ($hourlyEarnings[$hour] ?? 0) + 1;
        }

        return DailyRiderMetric::updateOrCreate(
            ['rider_id' => $riderId, 'date' => $date],
            [
                'orders_completed' => $completed->count(),
                'orders_cancelled' => $cancelled->count(),
                'total_earnings' => $totalEarnings,
                'total_distance_km' => $totalDistance,
                'total_online_minutes' => 0,
                'restaurant_breakdown' => $restaurantBreakdown,
                'time_slot_breakdown' => $slots,
                'hourly_earnings' => $hourlyEarnings,
            ]
        );
    }

    // ─── Private helpers ───

    private static function resolvePeriod(string $period, ?string $start, ?string $end): array
    {
        $now = Carbon::now();

        return match ($period) {
            'week' => [
                $now->copy()->startOfWeek(),
                $now->copy()->endOfWeek(),
                $now->copy()->subWeek()->startOfWeek(),
                $now->copy()->subWeek()->endOfWeek(),
            ],
            'month' => [
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth(),
                $now->copy()->subMonth()->startOfMonth(),
                $now->copy()->subMonth()->endOfMonth(),
            ],
            'custom' => [
                Carbon::parse($start),
                Carbon::parse($end),
                Carbon::parse($start)->subDays(Carbon::parse($end)->diffInDays(Carbon::parse($start)) + 1),
                Carbon::parse($start)->subDay(),
            ],
            default => [
                $now->copy()->startOfWeek(),
                $now->copy()->endOfWeek(),
                $now->copy()->subWeek()->startOfWeek(),
                $now->copy()->subWeek()->endOfWeek(),
            ],
        };
    }

    private static function aggregate(string $riderId, Carbon $start, Carbon $end): array
    {
        // Try from pre-aggregated metrics first
        $metrics = DailyRiderMetric::where('rider_id', $riderId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get();

        if ($metrics->isNotEmpty()) {
            return [
                'completed' => $metrics->sum('orders_completed'),
                'earnings' => $metrics->sum('total_earnings'),
                'distance' => $metrics->sum('total_distance_km'),
                'online_minutes' => $metrics->sum('total_online_minutes'),
            ];
        }

        // Fallback: live aggregation
        $orders = Order::where('rider_id', $riderId)
            ->whereBetween('created_at', [$start, $end])
            ->get();

        $completed = $orders->whereIn('status', ['delivered', 'completed']);
        $payoutSum = RiderPayout::where('rider_id', $riderId)
            ->whereBetween('created_at', [$start, $end])
            ->sum('total_payout');

        return [
            'completed' => $completed->count(),
            'earnings' => (float) $payoutSum,
            'distance' => $orders->sum('delivery_distance_km'),
            'online_minutes' => 0,
        ];
    }

    private static function getTopRestaurants(string $riderId, Carbon $start, Carbon $end): array
    {
        $orders = Order::where('rider_id', $riderId)
            ->whereIn('status', ['delivered', 'completed'])
            ->whereBetween('created_at', [$start, $end])
            ->select('restaurant_id', DB::raw('count(*) as total'))
            ->groupBy('restaurant_id')
            ->orderByDesc('total')
            ->limit(5)
            ->with('restaurant:id,name')
            ->get();

        return $orders->map(fn($o) => [
            'restaurant_id' => $o->restaurant_id,
            'name' => $o->restaurant?->name ?? 'Unknown',
            'order_count' => (int) $o->total,
        ])->values()->all();
    }

    private static function getTimeSlotAnalysis(string $riderId, Carbon $start, Carbon $end): array
    {
        $metrics = DailyRiderMetric::where('rider_id', $riderId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get();

        if ($metrics->isNotEmpty()) {
            $slots = ['0-3' => 0, '3-6' => 0, '6-9' => 0, '9-12' => 0, '12-15' => 0, '15-18' => 0, '18-21' => 0, '21-24' => 0];
            foreach ($metrics as $m) {
                $breakdown = $m->time_slot_breakdown ?? [];
                foreach ($slots as $slot => &$count) {
                    $count += $breakdown[$slot] ?? 0;
                }
            }
            return $slots;
        }

        // Fallback live query
        $orders = Order::where('rider_id', $riderId)
            ->whereIn('status', ['delivered', 'completed'])
            ->whereBetween('created_at', [$start, $end])
            ->get();

        $slots = ['0-3' => 0, '3-6' => 0, '6-9' => 0, '9-12' => 0, '12-15' => 0, '15-18' => 0, '18-21' => 0, '21-24' => 0];
        foreach ($orders as $order) {
            $hour = $order->created_at->hour;
            if ($hour < 3) $slots['0-3']++;
            elseif ($hour < 6) $slots['3-6']++;
            elseif ($hour < 9) $slots['6-9']++;
            elseif ($hour < 12) $slots['9-12']++;
            elseif ($hour < 15) $slots['12-15']++;
            elseif ($hour < 18) $slots['15-18']++;
            elseif ($hour < 21) $slots['18-21']++;
            else $slots['21-24']++;
        }

        return $slots;
    }

    private static function delta(float $current, float $previous): ?float
    {
        if ($previous == 0 && $current == 0) return 0;
        if ($previous == 0) return 100;
        return round((($current - $previous) / $previous) * 100, 1);
    }
}
