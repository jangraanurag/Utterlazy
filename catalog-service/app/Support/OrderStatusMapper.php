<?php

namespace App\Support;

final class OrderStatusMapper
{
    private const TO_COMMAND = [
        'pending' => 'PENDING',
        'confirmed' => 'CONFIRMED',
        'preparing' => 'PREPARING',
        'ready_for_pickup' => 'READY_FOR_PICKUP',
        'out_for_delivery' => 'OUT_FOR_DELIVERY',
        'delivered' => 'DELIVERED',
        'cancelled' => 'CANCELLED',
        'abandoned' => 'ABANDONED',
        'rescued' => 'RESCUED',
    ];

    public static function toCommand(?string $status): ?string
    {
        return self::TO_COMMAND[strtolower((string) $status)] ?? null;
    }

    /** Path of transitions the command service needs to reach a status from PENDING. */
    public static function pathTo(string $status): array
    {
        return [
            'PENDING' => [],
            'CONFIRMED' => ['CONFIRMED'],
            'PREPARING' => ['CONFIRMED', 'PREPARING'],
            'READY_FOR_PICKUP' => ['CONFIRMED', 'PREPARING', 'READY_FOR_PICKUP'],
            'OUT_FOR_DELIVERY' => ['CONFIRMED', 'PREPARING', 'READY_FOR_PICKUP', 'OUT_FOR_DELIVERY'],
            'DELIVERED' => ['CONFIRMED', 'PREPARING', 'READY_FOR_PICKUP', 'OUT_FOR_DELIVERY', 'DELIVERED'],
            'RESCUED' => ['CONFIRMED', 'PREPARING', 'READY_FOR_PICKUP', 'OUT_FOR_DELIVERY', 'RESCUED'],
        ][$status] ?? [];
    }
}
