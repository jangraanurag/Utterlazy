<?php

namespace App\Services\Analytics;

use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Time-travel feature export from ClickHouse order events.
 *
 * v1 exports per-restaurant counts/GMV as-of a timestamp. Static attributes
 * (rating, prep time, price) still come from MySQL when building a training set.
 */
class ClickHouseFeatureExporter
{
    /** @var callable(string):array<int,array<string,mixed>> */
    private $query;

    public function __construct(?callable $query = null)
    {
        $this->query = $query ?? fn (string $sql) => (new ClickHouseQueryClient)->rows($sql);
    }

    /** @return array<int,array<string,mixed>> */
    public function asOf(string $asOf, ?string $cityId = null, int $limit = 500): array
    {
        $timestamp = Carbon::parse($asOf)->format('Y-m-d H:i:s.v');

        if ($cityId !== null && ! preg_match('/^[0-9a-f-]{36}$/i', $cityId)) {
            throw new InvalidArgumentException('cityId must be a UUID');
        }

        $cityFilter = $cityId ? " AND city_id = '{$cityId}'" : '';
        $limit = max(1, min($limit, 5000));

        $rows = ($this->query)("
            SELECT
                restaurant_id,
                countIf(event_type = 'order.created') AS orders,
                countIf(event_type = 'order.status_changed' AND status = 'delivered') AS delivered,
                countIf(event_type = 'order.status_changed' AND status = 'cancelled') AS cancelled,
                sumIf(grand_total, event_type = 'order.created') AS gmv
            FROM order_events
            WHERE occurred_at <= toDateTime64('{$timestamp}', 3){$cityFilter}
            GROUP BY restaurant_id
            ORDER BY orders DESC
            LIMIT {$limit}
        ");

        return array_map(fn (array $row) => [
            'restaurant_id' => (string) ($row['restaurant_id'] ?? ''),
            'orders' => (int) ($row['orders'] ?? 0),
            'delivered' => (int) ($row['delivered'] ?? 0),
            'cancelled' => (int) ($row['cancelled'] ?? 0),
            'gmv' => round((float) ($row['gmv'] ?? 0), 2),
        ], $rows);
    }

    /** @param array<int,array<string,mixed>> $rows */
    public function toCsv(array $rows, string $asOf): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['restaurant_id', 'as_of', 'orders', 'delivered', 'cancelled', 'gmv'], ',', '"', '');

        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['restaurant_id'],
                $asOf,
                $row['orders'],
                $row['delivered'],
                $row['cancelled'],
                number_format((float) $row['gmv'], 2, '.', ''),
            ], ',', '"', '');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return (string) $csv;
    }
}
