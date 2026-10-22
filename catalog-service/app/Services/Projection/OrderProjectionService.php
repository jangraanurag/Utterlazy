<?php

namespace App\Services\Projection;

/**
 * Projects Java order-command events into a Laravel read model.
 *
 * This is deliberately NOT the live `orders` table. Cutover requires this
 * pipeline to be proven first; downstream consumers can then move off it.
 */
class OrderProjectionService
{
    public const CONSUMER = 'order-projection';

    public const SUPPORTED = ['order.created', 'order.status_changed'];

    private object $db;

    /** @param object|null $db DatabaseManager override for standalone tests */
    public function __construct(?object $db = null)
    {
        $this->db = $db ?? app('db');
    }

    /**
     * @return array{applied:bool, reason:string, order_id?:string}
     */
    public function apply(array $envelope): array
    {
        $eventId = (string) ($envelope['event_id'] ?? '');
        $eventType = (string) ($envelope['event_type'] ?? '');
        $data = $envelope['data'] ?? [];

        if ($eventId === '' || ! in_array($eventType, self::SUPPORTED, true)) {
            return ['applied' => false, 'reason' => 'unsupported_event'];
        }

        $orderId = (string) ($data['orderId'] ?? '');
        if ($orderId === '') {
            return ['applied' => false, 'reason' => 'missing_order_id'];
        }

        return $this->db->transaction(function () use ($eventId, $eventType, $data, $orderId, $envelope) {
            $claimed = $this->db->table('processed_events')->insertOrIgnore([
                'event_id' => $eventId,
                'consumer' => self::CONSUMER,
                'processed_at' => now(),
            ]);

            if (! $claimed) {
                return ['applied' => false, 'reason' => 'duplicate', 'order_id' => $orderId];
            }

            $existing = $this->db->table('projected_orders')->where('order_id', $orderId)->first();
            $incomingVersion = (int) ($data['version'] ?? 0);

            if ($existing && $incomingVersion < (int) $existing->version) {
                return ['applied' => false, 'reason' => 'stale_version', 'order_id' => $orderId];
            }

            $pricing = $data['pricing'] ?? [];
            $row = [
                'user_id' => $data['userId'] ?? null,
                'restaurant_id' => $data['restaurantId'] ?? null,
                'city_id' => $data['cityId'] ?? null,
                'channel' => strtolower((string) ($data['channel'] ?? 'app')),
                'status' => strtolower((string) ($data['status'] ?? 'pending')),
                'cancel_reason' => $data['cancelReason'] ?? null,
                'grand_total' => $pricing['grandTotal'] ?? 0,
                'tax' => $pricing['tax'] ?? 0,
                'vendor_discount' => $pricing['vendorDiscount'] ?? 0,
                'delivery_fee' => $pricing['deliveryFee'] ?? 0,
                'platform_fee' => $pricing['platformFee'] ?? 0,
                'coupon_discount' => $pricing['couponDiscount'] ?? 0,
                'commission' => $pricing['commission'] ?? 0,
                'commission_gst' => $pricing['commissionGst'] ?? 0,
                'net_payout' => $pricing['netPayout'] ?? 0,
                'pricing' => $pricing ? json_encode($pricing) : null,
                'version' => $incomingVersion,
                'event_type' => $eventType,
                'last_event_id' => $eventId,
                'last_event_at' => $envelope['occurred_at'] ?? now(),
                'projected_at' => now(),
            ];

            $this->db->table('projected_orders')->updateOrInsert(['order_id' => $orderId], $row);

            return ['applied' => true, 'reason' => 'projected', 'order_id' => $orderId];
        });
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        $last24h = now()->subDay();

        return [
            'total' => $this->db->table('projected_orders')->count(),
            'last_24h' => $this->db->table('projected_orders')->where('projected_at', '>=', $last24h)->count(),
            'latest' => $this->db->table('projected_orders')->orderByDesc('projected_at')->value('projected_at'),
            'by_status' => $this->db->table('projected_orders')
                ->selectRaw('status, COUNT(*) AS c')
                ->groupBy('status')
                ->pluck('c', 'status')
                ->all(),
        ];
    }
}
