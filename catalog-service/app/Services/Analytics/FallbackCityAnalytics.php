<?php

namespace App\Services\Analytics;

use Illuminate\Support\Facades\Log;

/**
 * Prefer ClickHouse for daily city facts, fall back to MySQL when the
 * analytics store is empty or unreachable. Command Center must never break
 * because the analytics profile is down.
 */
class FallbackCityAnalytics implements CityAnalyticsReader
{
    private string $source = 'mysql';

    public function __construct(
        private readonly CityAnalyticsReader $primary,
        private readonly CityAnalyticsReader $fallback,
    ) {
    }

    public function daily(array $cityIds, ?string $day = null): array
    {
        try {
            $rows = $this->primary->daily($cityIds, $day);

            if (! empty($rows)) {
                $this->source = $this->primary->source();

                return $rows;
            }

            $this->source = 'mysql_fallback_empty';
        } catch (\Throwable $e) {
            $this->source = 'mysql_fallback';
            $this->safeLog('Analytics read failed; falling back to MySQL: '.$e->getMessage());
        }

        return $this->fallback->daily($cityIds, $day);
    }

    public function source(): string
    {
        return $this->source;
    }

    private function safeLog(string $message): void
    {
        try {
            Log::warning($message);
        } catch (\Throwable) {
            // standalone tests
        }
    }
}
