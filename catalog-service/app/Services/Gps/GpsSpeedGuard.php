<?php

namespace App\Services\Gps;

use App\Services\RiderAssignmentService;
use Carbon\Carbon;

/**
 * Rejects implausible rider movement (spoofed location jumps).
 * Threshold is deliberately generous: 120 km/h sustained between pings.
 */
class GpsSpeedGuard
{
    public const MAX_KMH = 120.0;

    public function isPlausible(
        ?float $oldLat,
        ?float $oldLng,
        ?Carbon $oldAt,
        float $newLat,
        float $newLng,
        ?Carbon $newAt = null,
    ): bool {
        if ($oldLat === null || $oldLng === null || $oldAt === null) {
            return true;
        }

        $newAt ??= now();
        $seconds = max(1, (int) $oldAt->diffInSeconds($newAt, false));

        $distance = RiderAssignmentService::haversineDistance($oldLat, $oldLng, $newLat, $newLng);
        $speedKmh = $distance / ($seconds / 3600);

        return $speedKmh <= self::MAX_KMH;
    }
}
