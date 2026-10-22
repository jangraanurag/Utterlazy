<?php

namespace App\Services;

use App\Jobs\SendRiderNotification;
use App\Models\Order;
use App\Models\Rider;
use App\Models\RiderAssignment;
use App\Models\Trip;
use App\Models\TripStop;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RouteOptimizerService
{
    /**
     * Try to batch-assign multiple orders to one rider.
     * Checks for:
     *   1. Same-customer orders from different restaurants
     *   2. Same-restaurant orders going to nearby addresses
     *   3. Orders along an existing rider's route
     */
    public static function batchIfPossible(Order $order): void
    {
        $restaurant = $order->restaurant;
        if (! $restaurant) {
            return;
        }

        // 1. Check if order's customer has other preparing/ready orders
        $batchables = self::findBatchableOrders($order);

        if ($batchables->isEmpty()) {
            return;
        }

        // 2. Try to assign all batchable orders to the best rider
        self::assignBatchWithRoute($batchables);
    }

    /**
     * Find orders that can be batched with this one.
     */
    public static function findBatchableOrders(Order $order)
    {
        $orders = collect();

        // Same customer, different restaurants — cross-restaurant route
        $sameCustomerOrders = Order::where('user_id', $order->user_id)
            ->where('id', '!=', $order->id)
            ->whereNull('rider_id')
            ->whereIn('status', ['preparing', 'ready_for_pickup'])
            ->where('created_at', '>=', now()->subHours(2))
            ->with('restaurant')
            ->get();

        foreach ($sameCustomerOrders as $o) {
            $orders->push($o);
        }

        // Same restaurant, preparing/ready — same-restaurant batch
        $sameRestaurantOrders = Order::where('restaurant_id', $order->restaurant_id)
            ->where('id', '!=', $order->id)
            ->whereNull('rider_id')
            ->whereIn('status', ['preparing', 'ready_for_pickup'])
            ->where('created_at', '>=', now()->subHours(2))
            ->get();

        foreach ($sameRestaurantOrders as $o) {
            if (! $orders->contains('id', $o->id)) {
                $orders->push($o);
            }
        }

        // Max 3 orders per batch
        $orders = $orders->take(3);

        if ($orders->isEmpty()) {
            return $orders;
        }

        // Add the primary order
        $allOrders = collect([$order]);
        foreach ($orders as $o) {
            if (! $allOrders->contains('id', $o->id)) {
                $allOrders->push($o);
            }
        }

        return $allOrders;
    }

    /**
     * Assign a batch of orders to the best rider with route optimization.
     */
    public static function assignBatchWithRoute($orders): array
    {
        $results = [];

        if ($orders->isEmpty()) {
            return $results;
        }

        // Find the best rider for the combined route
        $rider = self::findBestRiderForBatch($orders);

        if (! $rider) {
            return $results;
        }

        DB::beginTransaction();
        try {
            $trip = Trip::create([
                'rider_id' => $rider->id,
                'stop_count' => 0,
                'total_distance_km' => 0,
                'total_earnings' => 0,
                'status' => 'active',
                'started_at' => now(),
            ]);

            $stops = [];
            $stopOrder = 1;
            $totalDistance = 0;
            $prevLat = $rider->latitude;
            $prevLng = $rider->longitude;

            // Group orders by restaurant for optimized stops
            $restaurantGroups = [];
            foreach ($orders as $order) {
                $restId = $order->restaurant_id;
                if (! isset($restaurantGroups[$restId])) {
                    $restaurantGroups[$restId] = [
                        'restaurant' => $order->restaurant,
                        'orders' => [],
                    ];
                }
                $restaurantGroups[$restId]['orders'][] = $order;
            }

            // Create pickup stops (one per restaurant)
            foreach ($restaurantGroups as $group) {
                $restaurant = $group['restaurant'];
                if (! $restaurant) {
                    continue;
                }

                $dist = RiderAssignmentService::haversineDistance(
                    $prevLat, $prevLng,
                    $restaurant->latitude, $restaurant->longitude
                );
                $totalDistance += $dist;

                foreach ($group['orders'] as $groupOrder) {
                    $stops[] = TripStop::create([
                        'trip_id' => $trip->id,
                        'order_id' => $groupOrder->id,
                        'stop_order' => $stopOrder,
                        'stop_type' => 'pickup',
                        'distance_from_prev_km' => round($dist, 2),
                    ]);

                    // Create assignment
                    RiderAssignmentService::createAssignment($groupOrder, $rider, $trip->id, $stopOrder, $dist);

                    $groupOrder->update([
                        'rider_id' => $rider->id,
                        'rider_assigned_at' => now(),
                    ]);

                    $stopOrder++;
                }

                $prevLat = $restaurant->latitude;
                $prevLng = $restaurant->longitude;
            }

            // Create delivery stops (optimized by customer proximity)
            $customerGroups = [];
            foreach ($orders as $order) {
                $userId = $order->user_id;
                if (! isset($customerGroups[$userId])) {
                    $customerGroups[$userId] = [
                        'lat' => $order->delivery_address_lat,
                        'lng' => $order->delivery_address_lng,
                        'orders' => [],
                    ];
                }
                $customerGroups[$userId]['orders'][] = $order;
            }

            foreach ($customerGroups as $group) {
                $dist = RiderAssignmentService::haversineDistance(
                    $prevLat, $prevLng,
                    $group['lat'], $group['lng']
                );
                $totalDistance += $dist;

                foreach ($group['orders'] as $groupOrder) {
                    $stops[] = TripStop::create([
                        'trip_id' => $trip->id,
                        'order_id' => $groupOrder->id,
                        'stop_order' => $stopOrder,
                        'stop_type' => 'delivery',
                        'distance_from_prev_km' => round($dist, 2),
                    ]);
                    $stopOrder++;
                }

                $prevLat = $group['lat'];
                $prevLng = $group['lng'];
            }

            $trip->update([
                'stop_count' => $stops->count(),
                'total_distance_km' => round($totalDistance, 2),
            ]);

            DB::commit();

            // Notify rider for each order
            foreach ($orders as $order) {
                try {
                    $eta = RiderAssignmentService::distanceEtaPackage($order, $rider);
                    SendRiderNotification::dispatch(
                        $rider->id,
                        array_merge([
                            'type' => 'new_assignment',
                            'assignment_id' => $order->id,
                            'order_id' => $order->id,
                            'restaurant_name' => $order->restaurant?->name ?? '',
                            'trip_id' => (string) $trip->id,
                            'total_stops' => (string) $stops->count(),
                            'route_info' => json_encode(self::buildRoutePreview($trip)),
                        ], $eta),
                        'New Multi-Order Route',
                        'You have '.$stops->count().' stops on this route!'
                    );
                } catch (\Throwable $e) {
                    Log::error('Batch assignment notification failed: '.$e->getMessage());
                }
            }

            $results[] = $trip;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Batch assignment failed: '.$e->getMessage());
        }

        return $results;
    }

    /**
     * Find the best rider for a batch of orders.
     */
    private static function findBestRiderForBatch($orders): ?Rider
    {
        $restaurant = $orders->first()->restaurant;
        if (! $restaurant || ! $restaurant->latitude || ! $restaurant->longitude) {
            return null;
        }

        $excludeIds = [];
        foreach ($orders as $order) {
            $assignedRiderIds = RiderAssignment::where('order_id', $order->id)
                ->pluck('rider_id')
                ->toArray();
            $excludeIds = array_merge($excludeIds, $assignedRiderIds);
        }

        // Use the first restaurant as anchor — we need a rider who is close
        $candidates = RiderAssignmentService::fetchCandidates(
            $restaurant->latitude,
            $restaurant->longitude,
            array_unique($excludeIds),
            $orders->first(),
        );

        if ($candidates->isEmpty()) {
            return null;
        }

        // Bonus riders with high capacity
        return $candidates->sortByDesc(function ($rider) {
            $capacity = $rider->current_order_id ? 0.5 : 1.5;

            return $rider->_score * $capacity;
        })->first();
    }

    /**
     * Build a human-readable route preview for the notification.
     */
    public static function buildRoutePreview(Trip $trip): array
    {
        $preview = [];
        $stops = $trip->stops()->with('order.restaurant')->orderBy('stop_order')->get();

        foreach ($stops as $stop) {
            $order = $stop->order;
            if ($stop->stop_type === 'pickup') {
                $preview[] = [
                    'type' => 'pickup',
                    'label' => 'Pickup from '.($order->restaurant?->name ?? 'Restaurant'),
                    'address' => $order->restaurant?->address ?? '',
                ];
            } else {
                $preview[] = [
                    'type' => 'delivery',
                    'label' => 'Deliver to customer',
                    'address' => $order->delivery_address_text ?? $order->delivery_address ?? '',
                ];
            }
        }

        return $preview;
    }

    /**
     * Get the current stop for a rider's active trip.
     */
    public static function getCurrentStop(Rider $rider): ?TripStop
    {
        $activeTrip = Trip::where('rider_id', $rider->id)
            ->where('status', 'active')
            ->first();

        if (! $activeTrip) {
            return null;
        }

        return TripStop::where('trip_id', $activeTrip->id)
            ->where('status', 'pending')
            ->orderBy('stop_order')
            ->first();
    }
}
