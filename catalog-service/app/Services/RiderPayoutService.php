<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Rider;
use App\Models\RiderPayout;
use App\Models\SystemConfig;
use Illuminate\Support\Facades\DB;

class RiderPayoutService
{
    /**
     * Calculate and create a payout record for a completed delivery.
     */
    public static function calculate(Order $order): ?RiderPayout
    {
        if (! $order->rider_id) {
            return null;
        }

        $rider = Rider::find($order->rider_id);
        if (! $rider) {
            return null;
        }

        // ─── Get configurable rates (Zomato-realistic) ───
        $basePay = (float) (SystemConfig::where('config_key', 'rider_base_pay')->value('config_value') ?? 15);
        $perKmRate = (float) (SystemConfig::where('config_key', 'rider_per_km_rate')->value('config_value') ?? 4);
        $waitPayPerMin = (float) (SystemConfig::where('config_key', 'rider_wait_pay_per_min')->value('config_value') ?? 2);
        $waitPayCap = (float) (SystemConfig::where('config_key', 'rider_wait_pay_cap')->value('config_value') ?? 20);
        $penaltyLate = (float) (SystemConfig::where('config_key', 'rider_penalty_late')->value('config_value') ?? 5);
        $incentiveTarget = (int) (SystemConfig::where('config_key', 'rider_incentive_target')->value('config_value') ?? 15);
        $incentiveBonus = (float) (SystemConfig::where('config_key', 'rider_incentive_bonus')->value('config_value') ?? 25);
        $maxPayoutCap = (float) (SystemConfig::where('config_key', 'rider_max_payout_cap')->value('config_value') ?? 60);

        // ─── 1. Distance Pay (capped at 7km — beyond that is flat) ───
        $deliveryDistance = $order->delivery_distance_km ?? 0;
        if ($deliveryDistance <= 0 && $order->restaurant && $order->delivery_address_lat) {
            $deliveryDistance = RiderAssignmentService::haversineDistance(
                $order->restaurant->latitude, $order->restaurant->longitude,
                $order->delivery_address_lat, $order->delivery_address_lng
            );
            $order->update(['delivery_distance_km' => round($deliveryDistance, 2)]);
        }
        // Only pay per-km for distance beyond first 2km (included in base), capped at 7km
        $payableKm = max(0, min($deliveryDistance, 7) - 2);
        $distancePay = round($payableKm * $perKmRate, 2);

        // ─── 2. Surge Pay (flat ₹5-10 boost, not % of base) ───
        $surgeMultiplier = RiderAssignmentService::getSurgeMultiplier();
        // Surge adds a small flat amount: ₹5 per 0.5x above 1.0
        $surgePay = round(max(0, ($surgeMultiplier - 1.0)) * 10, 2); // 1.5x = ₹5, 2.0x = ₹10

        // ─── 3. Wait Pay ───
        // Fault-first: minutes past the handover deadline are paid (kitchen late
        // or platform dispatched too early). Fallback: total wait > 15 min.
        $waitPay = 0;
        $handoverDelay = (int) ($order->handover_delay_minutes ?? 0);

        if ($handoverDelay > 0) {
            $waitPay = min($handoverDelay * $waitPayPerMin, $waitPayCap);
        } elseif ($order->rider_assigned_at && $order->rider_picked_up_at) {
            $waitMinutes = $order->rider_assigned_at->diffInMinutes($order->rider_picked_up_at);
            $extraWait = max(0, $waitMinutes - 15);
            $waitPay = min($extraWait * $waitPayPerMin, $waitPayCap);
        }

        // ─── 4. Tip (passed through 100%) ───
        $tipAmount = (float) ($order->customer_tip ?? 0);

        // ─── 5. Daily Incentive ───
        $todaysCount = $rider->todaysDeliveries() + 1;
        $incentive = 0;
        if ($todaysCount >= $incentiveTarget) {
            $alreadyGotIncentive = RiderPayout::where('rider_id', $rider->id)
                ->whereDate('created_at', today())
                ->where('incentive_bonus', '>', 0)
                ->exists();
            if (! $alreadyGotIncentive) {
                $incentive = $incentiveBonus;
            }
        }

        // ─── 6. Penalty ───
        $penalty = 0;
        if ($order->rider_assigned_at && $order->delivered_at) {
            $totalDeliveryMinutes = $order->rider_assigned_at->diffInMinutes($order->delivered_at);
            if ($totalDeliveryMinutes > 45) {
                $penalty = $penaltyLate;
            }
        }

        // ─── 7. Cash Collected (COD deduction) ───
        $cashCollected = 0;
        if ($order->payment_mode === 'cod') {
            $cashCollected = (float) $order->grand_total;
        }

        // ─── Total (with hard cap, tips always pass through) ───
        $deliveryPay = $basePay + $distancePay + $surgePay + $waitPay + $incentive - $penalty;
        $deliveryPay = min($deliveryPay, $maxPayoutCap); // Cap at ₹60 (excl tips)
        $totalPayout = round($deliveryPay + $tipAmount - $cashCollected, 2);

        // Create payout record
        $payout = DB::transaction(function () use (
            $rider, $order, $basePay, $distancePay, $surgePay, $surgeMultiplier,
            $waitPay, $tipAmount, $incentive, $penalty, $cashCollected, $totalPayout
        ) {
            $payout = RiderPayout::create([
                'rider_id' => $rider->id,
                'order_id' => $order->id,
                'base_pay' => $basePay,
                'distance_pay' => $distancePay,
                'surge_pay' => $surgePay,
                'wait_pay' => $waitPay,
                'tip_amount' => $tipAmount,
                'incentive_bonus' => $incentive,
                'penalty' => $penalty,
                'cash_collected' => $cashCollected,
                'total_payout' => $totalPayout,
                'surge_multiplier' => $surgeMultiplier,
            ]);

            // Update rider stats
            $rider->increment('total_deliveries');
            $rider->increment('total_earnings', max(0, $totalPayout));
            $rider->update(['current_order_id' => null]); // Free the rider

            return $payout;
        });

        return $payout;
    }

    /**
     * Settle (mark as paid) all unsettled payouts for a rider.
     */
    public static function settleForRider(string $riderId): int
    {
        return RiderPayout::where('rider_id', $riderId)
            ->where('is_settled', false)
            ->update([
                'is_settled' => true,
                'settled_at' => now(),
            ]);
    }

    /**
     * Bulk settle all unsettled payouts.
     */
    public static function settleAll(): int
    {
        return RiderPayout::where('is_settled', false)
            ->update([
                'is_settled' => true,
                'settled_at' => now(),
            ]);
    }

    /**
     * Get payout summary for admin dashboard.
     */
    public static function getSummary(): array
    {
        return [
            'total_unsettled' => round(RiderPayout::where('is_settled', false)->sum('total_payout'), 2),
            'total_settled_today' => round(
                RiderPayout::where('is_settled', true)
                    ->whereDate('settled_at', today())
                    ->sum('total_payout'),
                2
            ),
            'total_payouts_today' => round(
                RiderPayout::whereDate('created_at', today())->sum('total_payout'),
                2
            ),
            'avg_payout_per_delivery' => round(RiderPayout::avg('total_payout') ?? 0, 2),
            'total_tips_today' => round(
                RiderPayout::whereDate('created_at', today())->sum('tip_amount'),
                2
            ),
        ];
    }
}
