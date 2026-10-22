<?php

namespace App\Services\City;

use App\Models\Order;
use Carbon\Carbon;

/**
 * Opt-in hourly order cap per city (festival/supply protection).
 * cap <= 0 disables the guard; orders are counted by created_at in the last hour.
 */
class CityCapacityGuard
{
    public function allow(?string $cityId, int $cap, ?Carbon $at = null): bool
    {
        if ($cap <= 0 || ! $cityId) {
            return true;
        }

        $at ??= now();

        $count = Order::where('city_id', $cityId)
            ->where('created_at', '>=', $at->copy()->subHour())
            ->count();

        return $count < $cap;
    }
}
