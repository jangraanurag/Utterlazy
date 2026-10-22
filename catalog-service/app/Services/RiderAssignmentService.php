<?php

namespace App\Services;

use App\Events\NewAssignmentForRider;
use App\Events\RiderAssigned;
use App\Jobs\SendRiderNotification;
use App\Models\Order;
use App\Models\PendingAssignment;
use App\Models\Restaurant;
use App\Models\Rider;
use App\Models\RiderAssignment;
use App\Models\SystemConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RiderAssignmentService
{
    const ASSIGNMENT_TIMEOUT_SECONDS = 120;

    const MAX_ASSIGNMENT_ATTEMPTS = 3;

    const STALE_LOCATION_MINUTES = 30;

    const SHADOW_ETA_BUFFER_MINUTES = 3;

    /**
     * Main entry: assign best available rider.
     */
    public static function assign(Order $order): ?RiderAssignment
    {
        if ($order->rider_id) {
            return null;
        }

        $restaurant = $order->restaurant;
        if (! $restaurant || ! $restaurant->latitude || ! $restaurant->longitude) {
            return null;
        }

        $attemptedRiderIds = RiderAssignment::where('order_id', $order->id)
            ->pluck('rider_id')
            ->toArray();

        $attempt = count($attemptedRiderIds) + 1;
        $maxAttempts = (int) (SystemConfig::where('config_key', 'rider_max_assignment_attempts')->value('config_value') ?? self::MAX_ASSIGNMENT_ATTEMPTS);
        if ($attempt > $maxAttempts) {
            // All attempts exhausted — add to pending queue for later
            self::addToPendingQueue($order);

            return null;
        }

        $candidates = self::fetchCandidates($restaurant->latitude, $restaurant->longitude, $attemptedRiderIds, $order);
        if ($candidates->isEmpty()) {
            self::addToPendingQueue($order);

            return null;
        }

        $bestRider = $candidates->sortByDesc('_score')->first();

        $distance = self::haversineDistance(
            $bestRider->latitude, $bestRider->longitude,
            $restaurant->latitude, $restaurant->longitude
        );

        $assignment = self::createAssignmentRecord($order, $bestRider, $distance);

        self::notifyRider($assignment, $order, $bestRider);

        return $assignment;
    }

    /**
     * Create assignment record.
     */
    public static function createAssignment(Order $order, Rider $rider, ?int $tripId = null, int $stopOrder = 1, float $distance = 0): RiderAssignment
    {
        return self::createAssignmentRecord($order, $rider, $distance, $tripId, $stopOrder);
    }

    private static function createAssignmentRecord(Order $order, Rider $rider, float $distance, ?int $tripId = null, int $stopOrder = 1): RiderAssignment
    {
        $ra = new RiderAssignment;
        $ra->id = (string) \Illuminate\Support\Str::orderedUuid();
        $ra->order_id = $order->id;
        $ra->rider_id = $rider->id;
        $ra->status = 'pending';
        $ra->assignment_score = $rider->_score ?? 0;
        $ra->distance_km = round($distance, 2);
        $ra->assigned_at = now();
        $ra->save();

        return $ra;
    }

    private static function notifyRider(RiderAssignment $assignment, Order $order, Rider $rider): void
    {
        try {
            $eta = self::distanceEtaPackage($order, $rider);
            SendRiderNotification::dispatch(
                $rider->id,
                array_merge([
                    'type' => 'new_assignment',
                    'assignment_id' => $assignment->id,
                    'order_id' => $order->id,
                    'restaurant_name' => $order->restaurant?->name ?? '',
                    'distance_km' => (string) $assignment->distance_km,
                    'pickup_address' => $order->restaurant?->address ?? '',
                    'delivery_address' => $order->delivery_address ?? '',
                ], $eta),
                'New Delivery Assignment',
                'You have a new delivery request!'
            );
        } catch (\Throwable $e) {
            Log::error('SendRiderNotification dispatch failed: '.$e->getMessage());
        }

        try {
            broadcast(new NewAssignmentForRider($assignment));
        } catch (\Throwable $e) {
            Log::error('NewAssignmentForRider broadcast failed: '.$e->getMessage());
        }
    }

    /** Add order to pending queue when no rider available. */
    public static function addToPendingQueue(Order $order): void
    {
        if (PendingAssignment::where('order_id', $order->id)->exists()) {
            return;
        }

        $attempts = RiderAssignment::where('order_id', $order->id)->count();

        $pa = new PendingAssignment;
        $pa->id = (string) \Illuminate\Support\Str::orderedUuid();
        $pa->order_id = $order->id;
        $pa->attempt_count = $attempts;
        $pa->save();

        Log::info("Order {$order->id} added to pending assignment queue (attempts: {$attempts})");
    }

    /** Check pending queue and assign riders to waiting orders. */
    public static function processPendingQueue(): int
    {
        $assigned = 0;
        $pendings = PendingAssignment::with('order.restaurant')->get();

        foreach ($pendings as $pending) {
            $order = $pending->order;
            if (! $order) {
                $pending->delete();

                continue;
            }

            if ($order->rider_id || ! in_array($order->status, ['preparing', 'ready_for_pickup'])) {
                $pending->delete();

                continue;
            }

            $assignment = self::assign($order);
            if ($assignment) {
                $pending->delete();
                $assigned++;
            }
        }

        return $assigned;
    }

    /** Check if a newly online rider can take a pending order. */
    public static function checkPendingForRider(Rider $rider): ?RiderAssignment
    {
        if ($rider->current_order_id) {
            return null;
        }

        $pendings = PendingAssignment::with('order.restaurant')
            ->orderBy('created_at')
            ->get();

        foreach ($pendings as $pending) {
            $order = $pending->order;
            if (! $order || $order->rider_id) {
                $pending->delete();

                continue;
            }

            $orderRestaurant = $order->restaurant;
            if (! $orderRestaurant || ! $orderRestaurant->latitude || ! $orderRestaurant->longitude) {
                continue;
            }

            $distance = self::haversineDistance(
                $rider->latitude, $rider->longitude,
                $orderRestaurant->latitude, $orderRestaurant->longitude
            );
            if ($distance > 15) {
                continue;
            }

            $assignment = RiderAssignment::create([
                'order_id' => $order->id,
                'rider_id' => $rider->id,
                'status' => 'pending',
                'distance_km' => round($distance, 2),
                'assigned_at' => now(),
            ]);

            $rider->_score = 100;
            self::notifyRider($assignment, $order, $rider);

            $pending->delete();

            return $assignment;
        }

        return null;
    }

    public static function fetchCandidates(float $lat, float $lng, array $excludeIds, Order $order)
    {
        $staleThreshold = now()->subMinutes(self::STALE_LOCATION_MINUTES);

        $freeRiders = Rider::where('is_online', true)
            ->where('is_verified', true)
            ->where('gps_anomaly_count', '<', 3)
            ->whereNull('current_order_id')
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->where('updated_at', '>=', $staleThreshold)
            ->when(! empty($excludeIds), fn ($q) => $q->whereNotIn('id', $excludeIds))
            ->get();

        // Exploration floor: every 8th order prefers riders with < 20 trips,
        // if any are inside the same radius. Not a penalty to seniors — a
        // floor against new-rider starvation.
        if ($freeRiders->isNotEmpty() && (crc32((string) $order->id) % 8 === 0)) {
            $newRiders = $freeRiders->filter(fn ($rider) => (int) $rider->total_deliveries < 20);

            if ($newRiders->isNotEmpty()) {
                $freeRiders = $newRiders->values();
            }
        }

        // COD float cap: a rider above the outstanding-cash cap gets no more
        // cash orders until reconciliation. Prepaid offers are unaffected.
        if ($order->payment_mode === 'cod' && $freeRiders->isNotEmpty()) {
            $codCap = (float) (SystemConfig::where('config_key', 'rider_cod_cap')->value('config_value') ?? 5000);

            $outstanding = Order::whereIn('rider_id', $freeRiders->pluck('id'))
                ->where('payment_mode', 'cod')
                ->where('status', 'delivered')
                ->where('payment_status', '!=', 'paid')
                ->selectRaw('rider_id, SUM(grand_total) AS outstanding')
                ->groupBy('rider_id')
                ->pluck('outstanding', 'rider_id');

            $freeRiders = $freeRiders
                ->filter(fn ($rider) => ((float) ($outstanding[$rider->id] ?? 0) + (float) $order->grand_total) <= $codCap)
                ->values();
        }

        $candidates = self::scoreCandidates($freeRiders, $lat, $lng, $order);

        return $candidates;
    }

    /**
     * Batch assign: assign all pending orders in one pass.
     * Tries multi-order routing first, then falls back to single assignment.
     */
    public static function assignBatch(array $orders): array
    {
        $results = [];
        $assignedRiderIds = [];

        $allCandidates = Rider::where('is_online', true)
            ->where('is_verified', true)
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->where('updated_at', '>=', now()->subMinutes(self::STALE_LOCATION_MINUTES))
            ->get();

        $scoredAssignments = [];

        foreach ($orders as $order) {
            if ($order->rider_id) {
                continue;
            }
            $restaurant = $order->restaurant;
            if (! $restaurant || ! $restaurant->latitude || ! $restaurant->longitude) {
                continue;
            }

            $candidates = self::scoreCandidates($allCandidates, $restaurant->latitude, $restaurant->longitude, $order);

            foreach ($candidates as $rider) {
                $scoredAssignments[] = [
                    'order_id' => $order->id,
                    'rider_id' => $rider->id,
                    'score' => $rider->_score,
                    'distance' => $rider->_distance,
                    'restaurant_id' => $restaurant->id,
                ];
            }
        }

        usort($scoredAssignments, fn ($a, $b) => $b['score'] <=> $a['score']);

        $assignedOrders = [];
        $riderOrderCount = [];

        // Multi-order assignment: allow same rider to take multiple orders
        // from the same restaurant or same customer
        foreach ($scoredAssignments as $sa) {
            if (in_array($sa['order_id'], $assignedOrders)) {
                continue;
            }

            $riderId = $sa['rider_id'];
            $order = collect($orders)->firstWhere('id', $sa['order_id']);
            if (! $order || $order->rider_id) {
                continue;
            }

            // Allow rider to take multiple orders (max 3)
            $currentCount = $riderOrderCount[$riderId] ?? 0;
            if ($currentCount >= 3) {
                continue;
            }

            // Prevent same rider taking conflicting orders (different areas)
            if (isset($riderOrderCount[$riderId])) {
                if (! $self::areOrdersRoutable($riderId, $order)) {
                    continue;
                }
            }

            $assignment = RiderAssignment::create([
                'order_id' => $sa['order_id'],
                'rider_id' => $riderId,
                'status' => 'pending',
                'assignment_score' => $sa['score'],
                'distance_km' => round($sa['distance'], 2),
                'assigned_at' => now(),
            ]);

            try {
                $rider = Rider::find($sa['rider_id']);
                if ($rider) {
                    $eta = self::distanceEtaPackage($order, $rider);
                    SendRiderNotification::dispatch(
                        $riderId,
                        array_merge([
                            'type' => 'new_assignment',
                            'assignment_id' => $assignment->id,
                            'order_id' => $sa['order_id'],
                            'restaurant_name' => $order->restaurant?->name ?? '',
                            'distance_km' => (string) $assignment->distance_km,
                            'pickup_address' => $order->restaurant?->address ?? '',
                            'delivery_address' => $order->delivery_address ?? '',
                        ], $eta),
                        'New Delivery Assignment',
                        'You have a new delivery request!'
                    );
                }
            } catch (\Throwable $e) {
                Log::error('SendRiderNotification dispatch failed: '.$e->getMessage());
            }

            try {
                broadcast(new NewAssignmentForRider($assignment));
            } catch (\Throwable $e) {
                Log::error('NewAssignmentForRider broadcast failed: '.$e->getMessage());
            }

            $results[] = $assignment;
            $assignedOrders[] = $sa['order_id'];
            $riderOrderCount[$riderId] = $currentCount + 1;
        }

        return $results;
    }

    /** Check if orders can be combined for a rider (same route). */
    private static function areOrdersRoutable(string $riderId, Order $newOrder): bool
    {
        $existingAssignment = RiderAssignment::where('rider_id', $riderId)
            ->where('status', 'pending')
            ->with('order.restaurant')
            ->first();

        if (! $existingAssignment || ! $existingAssignment->order) {
            return true;
        }

        $existingRestaurant = $existingAssignment->order->restaurant;
        $newRestaurant = $newOrder->restaurant;

        if (! $existingRestaurant || ! $newRestaurant) {
            return false;
        }

        // Same restaurant — always routable
        if ($existingRestaurant->id === $newRestaurant->id) {
            return true;
        }

        // Same customer — always routable (customer collects all at once)
        if ($existingAssignment->order->user_id === $newOrder->user_id) {
            return true;
        }

        // Different areas: only routable if restaurants within 3 km
        $distBetween = self::haversineDistance(
            $existingRestaurant->latitude, $existingRestaurant->longitude,
            $newRestaurant->latitude, $newRestaurant->longitude
        );

        return $distBetween <= 3;
    }

    public static function accept(RiderAssignment $assignment): void
    {
        $riderName = null;

        DB::transaction(function () use ($assignment, &$riderName) {
            $assignment->update([
                'status' => 'accepted',
                'responded_at' => now(),
            ]);

            $rider = $assignment->rider;
            $order = $assignment->order;
            $riderName = $rider->name ?? 'Rider';

            $rider->update(['current_order_id' => $order->id]);

            $order->update([
                'rider_id' => $rider->id,
                'rider_assigned_at' => now(),
            ]);

            // Cancel other pending assignments for this order
            RiderAssignment::where('order_id', $order->id)
                ->where('id', '!=', $assignment->id)
                ->where('status', 'pending')
                ->update(['status' => 'cancelled']);

            // Remove from pending queue if present
            PendingAssignment::where('order_id', $order->id)->delete();
        });

        $order = $assignment->order->fresh();
        try {
            broadcast(new RiderAssigned($order));
        } catch (\Throwable $e) {
            Log::error('RiderAssigned broadcast failed: '.$e->getMessage());
        }

        try {
            $customer = $order->user;
            if ($customer) {
                $fcm = app(FcmService::class);
                $fcm->sendToUser($customer, 'customer', [
                    'type' => 'rider_assigned',
                    'order_id' => $order->id,
                    'rider_name' => $riderName ?? 'Rider',
                    'status' => $order->status,
                ], 'Rider Assigned', ($riderName ?? 'Rider').' is on the way to pick up your order!');
            }
        } catch (\Throwable $e) {
            Log::error('FCM sendToUser failed: '.$e->getMessage());
        }
    }

    public static function reject(RiderAssignment $assignment): ?RiderAssignment
    {
        $assignment->update([
            'status' => 'rejected',
            'responded_at' => now(),
        ]);

        $assignment->rider->recalculateAcceptanceRate();

        $order = $assignment->order;
        $order->update(['rider_id' => null, 'rider_assigned_at' => null]);

        return self::assign($order);
    }

    public static function expireStaleAssignments(): int
    {
        $cutoff = now()->subSeconds(self::ASSIGNMENT_TIMEOUT_SECONDS);

        $stale = RiderAssignment::where('status', 'pending')
            ->where('assigned_at', '<', $cutoff)
            ->get();

        $count = 0;
        foreach ($stale as $assignment) {
            $assignment->update(['status' => 'expired', 'responded_at' => now()]);
            $assignment->rider->recalculateAcceptanceRate();

            $order = $assignment->order;
            $order->update(['rider_id' => null, 'rider_assigned_at' => null]);

            self::assign($order);
            $count++;
        }

        return $count;
    }

    // ─── Scoring methods (unchanged from original) ───

    public static function scoreCandidates($riders, float $restaurantLat, float $restaurantLng, Order $order)
    {
        return $riders->map(function ($rider) use ($restaurantLat, $restaurantLng, $order) {
            return self::scoreCandidate($rider, $restaurantLat, $restaurantLng, $order);
        })->filter()->sortByDesc('_score');
    }

    public static function scoreCandidate(Rider $rider, float $restaurantLat, float $restaurantLng, Order $order): ?Rider
    {
        $distance = self::haversineDistance(
            $rider->latitude, $rider->longitude,
            $restaurantLat, $restaurantLng
        );

        if ($distance > 15) {
            return null;
        }

        $proximityScore = max(0, 100 - ($distance * 8));
        $qualityScore = self::effectiveQualityScore($rider);
        $ucbScore = self::ucbExplorationBonus($rider);
        $acceptanceScore = $rider->acceptance_rate ?? 100;

        $asymmetricCost = self::asymmetricCost($rider, $order, $distance);
        $costScore = max(0, 100 - $asymmetricCost);

        $spatialOutlierScore = self::spatialOutlierPenalty($rider, $restaurantLat, $restaurantLng);
        $costScore = min($costScore, $spatialOutlierScore);

        $utterlazyScore = (0.25 * $proximityScore)
                     + (0.25 * $qualityScore)
                     + (0.10 * $ucbScore)
                     + (0.10 * $acceptanceScore)
                     + (0.15 * ($rider->current_order_id ? 30 : 100))
                     + (0.15 * $costScore);

        $rider->_score = round($utterlazyScore, 4);
        $rider->_distance = $distance;

        return $rider;
    }

    // ─── Remaining helper methods from original ───

    public static function effectiveQualityScore(Rider $rider): float
    {
        $emaRating = $rider->ema_rating ?? $rider->avg_rating ?? 5.0;
        $restaurantAvg = (float) Order::where('rider_id', $rider->id)
            ->whereNotNull('restaurant_rating_for_rider')
            ->avg('restaurant_rating_for_rider');

        if ($restaurantAvg > 0) {
            return (($emaRating * 0.7) + ($restaurantAvg * 0.3)) * 20;
        }

        return $emaRating * 20;
    }

    public static function ucbExplorationBonus(Rider $rider): float
    {
        $totalAssignments = max(1, (int) RiderAssignment::count());
        $riderAssignments = max(1, RiderAssignment::where('rider_id', $rider->id)->count());

        return min(100, (2.0 * sqrt(log($totalAssignments) / $riderAssignments)) * 30);
    }

    public static function asymmetricCost(Rider $rider, Order $order, float $distance): float
    {
        $riderOrderIds = RiderAssignment::where('rider_id', $rider->id)
            ->where('status', 'accepted')
            ->pluck('order_id');

        $recentDeliveries = Order::whereIn('id', $riderOrderIds)
            ->whereIn('status', ['delivered', 'completed'])
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        if ($recentDeliveries < 3) {
            return 10;
        }

        $delayedOrders = Order::whereIn('id', $riderOrderIds)
            ->whereIn('status', ['delivered', 'completed'])
            ->where('created_at', '>=', now()->subDays(7))
            ->where(function ($q) {
                $q->whereNotNull('prep_delay_minutes')
                    ->orWhereRaw('TIMESTAMPDIFF(MINUTE, rider_picked_up_at, delivered_at) > 30');
            })
            ->count();

        $tardinessRate = $delayedOrders / max(1, $recentDeliveries);
        $tardinessPenalty = $tardinessRate * 3.0 * 50;

        $travelTimeMinutes = ($distance / 0.33) + 1;
        $prepTimeRemaining = max(0, ($order->prep_time_minutes ?? 10) - 2);
        $earliness = $prepTimeRemaining - $travelTimeMinutes;
        $earlyPenalty = $earliness > 5 ? ($earliness / 10) * 1.0 * 5 : 0;

        return $tardinessPenalty + $earlyPenalty;
    }

    public static function spatialOutlierPenalty(Rider $rider, float $restaurantLat, float $restaurantLng): float
    {
        $nearbyRiders = Rider::where('is_online', true)
            ->where('is_verified', true)
            ->where('id', '!=', $rider->id)
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->get();

        if ($nearbyRiders->isEmpty()) {
            return 100;
        }

        $minDistToPeer = $nearbyRiders->map(function ($peer) use ($rider) {
            return self::haversineDistance(
                $rider->latitude, $rider->longitude,
                $peer->latitude, $peer->longitude
            );
        })->min();

        if ($minDistToPeer > 8) {
            return max(50, 100 - (($minDistToPeer - 8) * 10));
        }

        return 100;
    }

    public static function updateEmaRating(Rider $rider, float $newRating): void
    {
        $oldEma = $rider->ema_rating ?? $rider->avg_rating ?? 5.0;
        $newEma = (0.3 * $newRating) + (0.7 * $oldEma);
        $rider->update(['ema_rating' => round($newEma, 4)]);
    }

    public static function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2)
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
           * sin($dLng / 2) * sin($dLng / 2);

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    public static function distanceEtaPackage(Order $order, ?Rider $rider = null): array
    {
        $restaurant = $order->restaurant;
        $riderToRestaurantKm = 0.0;
        $restaurantToCustomerKm = 0.0;

        if ($restaurant && $restaurant->latitude && $restaurant->longitude) {
            if ($rider && $rider->latitude && $rider->longitude) {
                $riderToRestaurantKm = self::haversineDistance(
                    $rider->latitude, $rider->longitude,
                    $restaurant->latitude, $restaurant->longitude
                );
            }
            if ($order->delivery_address_lat && $order->delivery_address_lng) {
                $restaurantToCustomerKm = self::haversineDistance(
                    $restaurant->latitude, $restaurant->longitude,
                    $order->delivery_address_lat, $order->delivery_address_lng
                );
            }
        }

        $riderEta = $rider && $rider->latitude && $rider->longitude && $restaurant?->latitude
            ? self::calculateSmartEta(
                $rider->latitude, $rider->longitude,
                $restaurant->latitude, $restaurant->longitude,
                $order->status,
                max(0, ($order->prep_time_minutes ?? 10) - 2)
            )
            : ['eta_min' => (int) ceil(self::estimateMinutesFromDistance($riderToRestaurantKm)), 'traffic' => 'Normal', 'weather' => 'Clear'];

        $customerEta = $restaurant?->latitude && $order->delivery_address_lat
            ? self::calculateSmartEta(
                $restaurant->latitude, $restaurant->longitude,
                $order->delivery_address_lat, $order->delivery_address_lng,
                'out_for_delivery'
            )
            : ['eta_min' => (int) ceil(self::estimateMinutesFromDistance($restaurantToCustomerKm)), 'traffic' => 'Normal', 'weather' => 'Clear'];

        return [
            'rider_to_restaurant_distance_km' => round($riderToRestaurantKm, 2),
            'rider_to_restaurant_distance_m' => (int) round($riderToRestaurantKm * 1000),
            'rider_to_restaurant_eta_min' => $riderEta['eta_min'],
            'restaurant_to_customer_distance_km' => round($restaurantToCustomerKm, 2),
            'restaurant_to_customer_distance_m' => (int) round($restaurantToCustomerKm * 1000),
            'restaurant_to_customer_eta_min' => $customerEta['eta_min'],
            'traffic' => $riderEta['traffic'],
            'weather' => $riderEta['weather'],
        ];
    }

    public static function estimateMinutesFromDistance(float $distanceKm): float
    {
        return ($distanceKm / 0.33) + 1;
    }

    public static function calculateSmartEta(
        float $fromLat, float $fromLng,
        float $toLat, float $toLng,
        ?string $orderStatus = null,
        int $prepTimeRemaining = 0
    ): array {
        $distanceKm = self::haversineKm($fromLat, $fromLng, $toLat, $toLng);
        $hour = (int) now()->format('H');
        $dayOfWeek = (int) now()->format('w');
        $trafficMultiplier = 1.0;

        if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
            if (($hour >= 8 && $hour < 10) || ($hour >= 17 && $hour < 20)) {
                $trafficMultiplier = 1.35;
            } elseif ($hour >= 12 && $hour < 14) {
                $trafficMultiplier = 1.20;
            } elseif (($hour >= 7 && $hour < 8) || ($hour >= 20 && $hour < 22)) {
                $trafficMultiplier = 1.15;
            }
        } else {
            if ($hour >= 10 && $hour < 20) {
                $trafficMultiplier = 1.10;
            }
        }

        $weatherMultiplier = 1.0;
        $month = (int) now()->format('m');
        if ($month >= 6 && $month <= 9) {
            $weatherMultiplier = 1.25;
        } elseif ($month >= 4 && $month <= 5) {
            $weatherMultiplier = 1.10;
        }

        $baseTravelMin = ($distanceKm / 0.33) + 1;
        $adjustedTravelMin = $baseTravelMin * $trafficMultiplier * $weatherMultiplier;

        $totalMin = $adjustedTravelMin;
        if ($orderStatus === 'preparing' || $orderStatus === 'ready_for_pickup' || $orderStatus === null) {
            $totalMin += max(0, $prepTimeRemaining) + 2;
        }

        return [
            'distance_km' => round($distanceKm, 2),
            'distance_m' => (int) round($distanceKm * 1000),
            'eta_min' => max(1, (int) ceil($totalMin)),
            'traffic' => $trafficMultiplier >= 1.30 ? 'Heavy traffic' : ($trafficMultiplier >= 1.15 ? 'Moderate traffic' : 'Normal'),
            'weather' => $weatherMultiplier >= 1.20 ? 'Rainy / Monsoon' : ($weatherMultiplier >= 1.10 ? 'Hot weather' : 'Clear'),
        ];
    }

    public static function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2)
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
           * sin($dLng / 2) * sin($dLng / 2);

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    public static function predictPrepTime(int $restaurantId): int
    {
        $recentOrders = Order::where('restaurant_id', $restaurantId)
            ->whereNotNull('prep_time_minutes')
            ->where('created_at', '>=', now()->subDays(30))
            ->orderByDesc('created_at')
            ->take(20)
            ->pluck('prep_time_minutes');

        if ($recentOrders->isEmpty()) {
            $restaurant = Restaurant::find($restaurantId);

            return $restaurant->avg_prep_time_minutes ?? 15;
        }

        $weights = [];
        $totalWeight = 0;
        $count = $recentOrders->count();

        foreach ($recentOrders as $i => $prepTime) {
            $weight = ($count - $i) / $count;
            $weights[] = $prepTime * $weight;
            $totalWeight += $weight;
        }

        return (int) round(array_sum($weights) / max(1, $totalWeight));
    }

    public static function getSurgeMultiplier(): float
    {
        $pendingOrders = Order::whereIn('status', ['ready_for_pickup', 'confirmed', 'preparing'])->count();
        $onlineRiders = Rider::where('is_online', true)->where('is_verified', true)->count();

        $surge = 1.0;
        if ($onlineRiders > 0) {
            $ratio = $pendingOrders / $onlineRiders;
            if ($ratio > 3) {
                $surge = 2.0;
            } elseif ($ratio > 2) {
                $surge = 1.5;
            }
        }

        $hour = (int) now()->format('H');
        if (($hour >= 12 && $hour <= 14) || ($hour >= 19 && $hour <= 22)) {
            $surge += 0.25;
        }

        return round(min($surge, 3.0), 2);
    }

    /**
     * Find batchable orders — orders from nearby restaurants going to same area.
     */
    public static function findBatchableOrders(Order $primaryOrder): array
    {
        $primaryOrder->load('restaurant', 'address');
        $primaryLat = $primaryOrder->restaurant?->latitude;
        $primaryLng = $primaryOrder->restaurant?->longitude;

        if (! $primaryLat || ! $primaryLng) {
            return [];
        }

        $nearbyOrders = Order::where('id', '!=', $primaryOrder->id)
            ->whereIn('status', ['ready_for_pickup', 'confirmed'])
            ->with(['restaurant', 'address'])
            ->get();

        $batchable = [];
        $maxBatch = 3;
        $maxDistanceKm = 1.5;

        foreach ($nearbyOrders as $order) {
            if (count($batchable) >= $maxBatch) {
                break;
            }
            if (! $order->restaurant?->latitude || ! $order->address?->latitude) {
                continue;
            }

            // Restaurant distance from primary order's restaurant
            $restDist = self::haversine(
                $primaryLat, $primaryLng,
                $order->restaurant->latitude, $order->restaurant->longitude
            );

            // Customer distance from primary order's customer
            $custDist = self::haversine(
                $primaryOrder->address?->latitude, $primaryOrder->address?->longitude,
                $order->address->latitude, $order->address->longitude
            );

            if ($restDist <= $maxDistanceKm && $custDist <= $maxDistanceKm * 1.5) {
                $batchable[] = [
                    'order' => $order,
                    'restaurant_distance_km' => round($restDist, 2),
                    'customer_distance_km' => round($custDist, 2),
                ];
            }
        }

        return $batchable;
    }

    protected static function haversine(?float $lat1, ?float $lng1, ?float $lat2, ?float $lng2): float
    {
        if (! $lat1 || ! $lng1 || ! $lat2 || ! $lng2) {
            return 999;
        }
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
