<?php

namespace App\Services;

use App\Models\Order;

class RiderPayCalculator
{
    const float RATE_PER_FULL_KM = 12.0;
    const float RATE_HALF_KM = 6.0;
    const float LEFTOVER_THRESHOLD_LOW_M = 200;
    const float LEFTOVER_THRESHOLD_HIGH_M = 900;

    /**
     * Calculate pay for a single delivery based on §1 formula.
     *
     * Rules:
     * - ₹12 per completed km
     * - leftover < 200m → ₹0
     * - 200m ≤ leftover ≤ 900m → ₹6
     * - leftover > 900m → round up to full km (paid at ₹12, not ₹6)
     */
    public static function calculate(float $distanceKm): array
    {
        $distanceMeters = $distanceKm * 1000;
        $fullKm = (int) floor($distanceKm);

        $leftoverMeters = $distanceMeters - ($fullKm * 1000);
        $leftoverPay = 0.0;
        $roundedUp = false;

        if ($leftoverMeters < self::LEFTOVER_THRESHOLD_LOW_M) {
            // < 200m: no pay for leftover
            $leftoverPay = 0.0;
        } elseif ($leftoverMeters <= self::LEFTOVER_THRESHOLD_HIGH_M) {
            // 200m ≤ leftover ≤ 900m: ₹6
            $leftoverPay = self::RATE_HALF_KM;
        } else {
            // > 900m: round up, count as full km
            $fullKm += 1;
            $roundedUp = true;
            $leftoverPay = 0.0;
        }

        $totalPay = ($fullKm * self::RATE_PER_FULL_KM) + $leftoverPay;

        return [
            'total_distance_km' => round($distanceKm, 2),
            'full_km' => $fullKm,
            'leftover_meters' => round($leftoverMeters, 0),
            'leftover_pay' => $leftoverPay,
            'rounded_up' => $roundedUp,
            'distance_pay' => round($totalPay, 2),
        ];
    }

    /**
     * Calculate and update order with pay info.
     * Returns the distance_pay component only.
     */
    public static function calculateForOrder(Order $order): array
    {
        $distanceKm = $order->delivery_distance_km ?? 0;

        if ($distanceKm <= 0 && $order->restaurant && $order->delivery_address_lat) {
            // Use actual routed distance from existing field or compute haversine as fallback
            $distanceKm = RiderAssignmentService::haversineDistance(
                $order->restaurant->latitude, $order->restaurant->longitude,
                $order->delivery_address_lat, $order->delivery_address_lng
            );
            $distanceKm = max(0, $distanceKm);
        }

        return self::calculate($distanceKm);
    }
}
