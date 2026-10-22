<?php

namespace App\Services\Analytics;

interface CityAnalyticsReader
{
    /**
     * Daily city facts, keyed by city_id.
     *
     * @param string[] $cityIds
     * @return array<string, array{orders:int, delivered:int, cancelled:int, no_rider:int, gmv:float}>
     */
    public function daily(array $cityIds, ?string $day = null): array;

    /** Where the current payload came from: clickhouse | mysql | mysql_fallback. */
    public function source(): string;
}
