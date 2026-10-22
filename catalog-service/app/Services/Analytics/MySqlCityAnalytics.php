<?php

namespace App\Services\Analytics;

use App\Models\Order;

class MySqlCityAnalytics implements CityAnalyticsReader
{
    public function daily(array $cityIds, ?string $day = null): array
    {
        if (empty($cityIds)) {
            return [];
        }

        $day ??= now()->toDateString();

        return Order::whereIn('city_id', $cityIds)
            ->whereDate('created_at', $day)
            ->selectRaw("
                city_id,
                COUNT(*) AS orders,
                SUM(status = 'delivered') AS delivered,
                SUM(status = 'cancelled') AS cancelled,
                SUM(status = 'cancelled' AND cancel_reason IN ('no_rider','customer_unreachable')) AS no_rider,
                COALESCE(SUM(grand_total), 0) AS gmv
            ")
            ->groupBy('city_id')
            ->get()
            ->mapWithKeys(fn ($row) => [
                (string) $row->city_id => [
                    'orders' => (int) $row->orders,
                    'delivered' => (int) $row->delivered,
                    'cancelled' => (int) $row->cancelled,
                    'no_rider' => (int) $row->no_rider,
                    'gmv' => round((float) $row->gmv, 2),
                ],
            ])
            ->all();
    }

    public function source(): string
    {
        return 'mysql';
    }
}
