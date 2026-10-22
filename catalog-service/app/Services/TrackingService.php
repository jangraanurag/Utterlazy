<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Rider;

class TrackingService
{
    public function getOrderTracking(Order $order): array
    {
        $order->load(['items.menuItem', 'restaurant', 'rider', 'address']);

        $timeline = $this->buildTimeline($order);
        $riderLocation = $this->getRiderLocation($order->rider);
        $etas = $this->calculateETAs($order, $riderLocation);

        $progress = match ($order->status) {
            'pending' => 0,
            'confirmed' => 15,
            'preparing' => 30,
            'ready_for_pickup' => 50,
            'out_for_delivery' => 70,
            'rider_arrived' => 85,
            'delivered', 'completed' => 100,
            'cancelled' => 0,
            default => 0,
        };

        return [
            'order_id' => $order->id,
            'status' => $order->status,
            'progress' => $progress,
            'restaurant' => $order->restaurant ? [
                'name' => $order->restaurant->name,
                'address' => $order->restaurant->address,
                'latitude' => $order->restaurant->latitude,
                'longitude' => $order->restaurant->longitude,
                'logo_url' => $order->restaurant->logo_url,
            ] : null,
            'rider' => $order->rider && $order->rider->is_verified ? [
                'name' => $order->rider->name,
                'phone' => $order->rider->phone,
                'latitude' => $riderLocation['lat'],
                'longitude' => $riderLocation['lng'],
                'vehicle_type' => $order->rider->vehicle_type,
                'vehicle_number' => $order->rider->vehicle_number,
                'rating' => $order->rider->avg_rating,
            ] : null,
            'customer' => [
                'latitude' => $order->address?->latitude,
                'longitude' => $order->address?->longitude,
                'address' => $order->address ? ($order->address->flat_building.', '.$order->address->street) : null,
            ],
            'etas' => $etas,
            'timeline' => $timeline,
            'delivery_otp' => $order->delivery_otp,
            'prep_time_minutes' => $order->prep_time_minutes,
            'grand_total' => $order->grand_total,
            'payment_mode' => $order->payment_mode,
            'created_at' => $order->created_at->toISOString(),
        ];
    }

    protected function buildTimeline(Order $order): array
    {
        $steps = [
            ['key' => 'placed', 'label' => 'Order Placed', 'time' => $order->created_at],
            ['key' => 'confirmed', 'label' => 'Confirmed by Restaurant', 'time' => $order->accepted_at ?? $order->confirmed_at],
            ['key' => 'preparing', 'label' => 'Preparing your food', 'time' => $order->preparing_at ?? $order->prep_deadline_at?->subMinutes($order->prep_time_minutes ?? 15)],
            ['key' => 'ready', 'label' => 'Ready for Pickup', 'time' => $order->ready_for_pickup_at],
            ['key' => 'picked_up', 'label' => 'Rider picked up order', 'time' => $order->picked_up_at],
            ['key' => 'out_for_delivery', 'label' => 'Out for delivery', 'time' => $order->out_for_delivery_at],
            ['key' => 'delivered', 'label' => 'Delivered', 'time' => $order->delivered_at],
        ];

        $currentIndex = null;
        $statusOrder = ['pending', 'confirmed', 'preparing', 'ready_for_pickup', 'picked_up', 'out_for_delivery', 'delivered', 'completed'];
        $currentStatus = $order->status;

        foreach ($statusOrder as $i => $s) {
            if ($s === $currentStatus && $currentIndex === null) {
                $currentIndex = $i;
            }
        }

        return array_map(function ($step, $index) use ($currentIndex) {
            $isCompleted = $step['time'] !== null;
            $isCurrent = $currentIndex !== null && $index === $currentIndex;

            return [
                'key' => $step['key'],
                'label' => $step['label'],
                'time' => $step['time']?->toISOString(),
                'status' => $isCompleted ? 'completed' : ($isCurrent ? 'active' : 'pending'),
            ];
        }, $steps, array_keys($steps));
    }

    protected function getRiderLocation(?Rider $rider): array
    {
        if (! $rider || ! $rider->is_online) {
            return ['lat' => null, 'lng' => null];
        }

        return [
            'lat' => $rider->latitude,
            'lng' => $rider->longitude,
        ];
    }

    protected function calculateETAs(Order $order, array $riderLocation): array
    {
        $result = [
            'rider_to_restaurant_min' => null,
            'restaurant_to_customer_min' => null,
            'total_eta_min' => null,
            'estimated_delivery_time' => null,
        ];

        if ($order->status === 'out_for_delivery' && $riderLocation['lat'] && $order->address) {
            $distance = $this->haversine(
                $riderLocation['lat'], $riderLocation['lng'],
                $order->address->latitude, $order->address->longitude
            );
            $etaMin = max(ceil($distance / 30 * 60), 5);
            $result['restaurant_to_customer_min'] = $etaMin;
            $result['total_eta_min'] = $etaMin;
            $result['estimated_delivery_time'] = now()->addMinutes($etaMin)->toISOString();
        }

        if ($order->status === 'preparing' && $order->prep_deadline_at) {
            $remainingPrep = max(0, now()->diffInMinutes($order->prep_deadline_at, false));
            $deliveryEta = $remainingPrep + 15;
            $result['total_eta_min'] = ceil($deliveryEta);
            $result['estimated_delivery_time'] = now()->addMinutes((int) ceil($deliveryEta))->toISOString();
        }

        return $result;
    }

    protected function haversine(?float $lat1, ?float $lng1, ?float $lat2, ?float $lng2): float
    {
        if (! $lat1 || ! $lng1 || ! $lat2 || ! $lng2) {
            return 0;
        }
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
