<?php

namespace App\Services\Analytics;

class ClickHouseCityAnalytics implements CityAnalyticsReader
{
    public function __construct(private readonly ClickHouseQuery $query)
    {
    }

    public function daily(array $cityIds, ?string $day = null): array
    {
        if (empty($cityIds)) {
            return [];
        }

        $day ??= now()->toDateString();
        $ids = implode(',', array_map(
            fn ($id) => "'".str_replace("'", "", (string) $id)."'",
            $cityIds,
        ));

        $rows = $this->query->rows("
            SELECT
                city_id,
                sum(orders) AS orders,
                sum(delivered) AS delivered,
                sum(cancelled) AS cancelled,
                sum(no_rider) AS no_rider,
                sum(gmv) AS gmv
            FROM city_daily
            WHERE day = toDate('{$day}')
              AND city_id IN ({$ids})
            GROUP BY city_id
        ");

        $out = [];
        foreach ($rows as $row) {
            $cityId = (string) ($row['city_id'] ?? '');
            if ($cityId === '') {
                continue;
            }

            $out[$cityId] = [
                'orders' => (int) ($row['orders'] ?? 0),
                'delivered' => (int) ($row['delivered'] ?? 0),
                'cancelled' => (int) ($row['cancelled'] ?? 0),
                'no_rider' => (int) ($row['no_rider'] ?? 0),
                'gmv' => round((float) ($row['gmv'] ?? 0), 2),
            ];
        }

        return $out;
    }

    public function source(): string
    {
        return 'clickhouse';
    }
}
