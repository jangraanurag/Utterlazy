<?php

namespace App\Services\Analytics;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Projects the transactional outbox into ClickHouse facts.
 *
 * Guarantees:
 * - one row per event (dedupe table `analytics_projected_events`),
 * - a failed insert is not marked, so it is retried on the next run,
 * - ClickHouse `ReplacingMergeTree` is a second safety net on `event_id`.
 *
 * Disabled unless ANALYTICS_ENABLED=true. The operational MySQL path is
 * untouched when this is off.
 */
class AnalyticsProjector
{
    public const TABLE_ORDER = 'order_events';
    public const TABLE_PAYMENT = 'payment_events';
    public const TABLE_AD = 'ad_events';

    private const MAP = [
        'order.created' => self::TABLE_ORDER,
        'order.status_changed' => self::TABLE_ORDER,
        'payment.status_changed' => self::TABLE_PAYMENT,
        'wa_placement.impression' => self::TABLE_AD,
        'wa_placement.click' => self::TABLE_AD,
    ];

    private object $db;

    public function __construct(
        private readonly AnalyticsSink $sink,
        private readonly ?bool $enabledOverride = null,
        ?object $db = null,
    ) {
        $this->db = $db ?? app('db');
    }

    public function enabled(): bool
    {
        if ($this->enabledOverride !== null) {
            return $this->enabledOverride;
        }

        try {
            return (bool) config('services.analytics.enabled', false);
        } catch (\Throwable) {
            return false;
        }
    }

    public function project(int $limit = 500): array
    {
        if (! $this->enabled()) {
            return ['projected' => 0, 'failed' => 0, 'skipped' => 0, 'disabled' => true];
        }

        $rows = $this->db->table('outbox')
            ->whereIn('event_type', array_keys(self::MAP))
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('analytics_projected_events')
                    ->whereColumn('analytics_projected_events.event_id', 'outbox.event_id');
            })
            ->orderBy('occurred_at')
            ->limit($limit)
            ->get();

        $projected = 0;
        $failed = 0;

        foreach ($rows as $row) {
            $table = self::MAP[$row->event_type] ?? null;

            if (! $table) {
                continue;
            }

            try {
                $payload = is_array($row->payload) ? $row->payload : json_decode((string) $row->payload, true);
                $payload = is_array($payload) ? $payload : [];

                $this->sink->insert($table, [$this->rowFor($table, $row, $payload)]);

                $this->db->table('analytics_projected_events')->insertOrIgnore([
                    'event_id' => $row->event_id,
                    'table_name' => $table,
                    'projected_at' => now(),
                ]);

                $projected++;
            } catch (\Throwable $e) {
                $failed++;

                try {
                    Log::warning('Analytics projection failed.', [
                        'event_id' => $row->event_id,
                        'event_type' => $row->event_type,
                        'error' => $e->getMessage(),
                    ]);
                } catch (\Throwable) {
                    // standalone tests
                }
            }
        }

        return ['projected' => $projected, 'failed' => $failed, 'skipped' => 0, 'disabled' => false];
    }

    private function rowFor(string $table, object $row, array $payload): array
    {
        $occurredAt = Carbon::parse($row->occurred_at)->format('Y-m-d H:i:s.v');

        $base = [
            'event_id' => (string) $row->event_id,
            'event_type' => (string) $row->event_type,
            'occurred_at' => $occurredAt,
            'city_id' => (string) ($payload['city_id'] ?? $row->city_id ?? ''),
            'restaurant_id' => (string) ($payload['restaurant_id'] ?? ''),
        ];

        if ($table === self::TABLE_ORDER) {
            return $base + [
                'order_id' => (string) ($payload['order_id'] ?? $row->aggregate_id),
                'user_id' => (string) ($payload['user_id'] ?? ''),
                'channel' => (string) ($payload['channel'] ?? 'app'),
                'status' => (string) ($payload['status'] ?? ''),
                'from_status' => (string) ($payload['from_status'] ?? ''),
                'payment_mode' => (string) ($payload['payment_mode'] ?? ''),
                'grand_total' => number_format((float) ($payload['grand_total'] ?? 0), 2, '.', ''),
            ];
        }

        if ($table === self::TABLE_PAYMENT) {
            return $base + [
                'order_id' => (string) ($payload['order_id'] ?? $row->aggregate_id),
                'user_id' => (string) ($payload['user_id'] ?? ''),
                'payment_mode' => (string) ($payload['payment_mode'] ?? ''),
                'payment_status' => (string) ($payload['payment_status'] ?? ''),
                'grand_total' => number_format((float) ($payload['grand_total'] ?? 0), 2, '.', ''),
            ];
        }

        return $base + [
            'placement_id' => (string) ($payload['placement_id'] ?? ''),
            'amount' => number_format((float) ($payload['amount'] ?? 0), 2, '.', ''),
        ];
    }
}
