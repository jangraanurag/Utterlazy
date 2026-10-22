<?php

namespace App\Services;

use App\Models\Order;
use App\Support\OrderStatusMapper;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Shadow-mode client for the Wave 2 Java order-command service.
 *
 * It NEVER participates in the customer request: it mirrors order creation and
 * status changes into a separate database so parity can be measured. Failures
 * are logged and ignored.
 */
class OrderCommandShadowClient
{
    public function enabled(): bool
    {
        return (bool) config('services.order_command.shadow_enabled', false)
            && (bool) config('services.order_command.url');
    }

    public function idempotencyKey(Order $order): string
    {
        return 'shadow-'.$order->id;
    }

    public function mirror(Order $order): void
    {
        if (! $this->enabled()) {
            return;
        }

        $order->loadMissing('items');

        $existing = $this->fetch($order);

        if ($existing === null) {
            $created = $this->create($order);

            if ($created === null) {
                return;
            }

            $existing = $created;
        }

        $this->syncStatus($order, $existing);
    }

    public function fetch(Order $order): ?array
    {
        try {
            $response = Http::timeout(5)->acceptJson()->get($this->url('/api/orders/by-idempotency'), [
                'userId' => $order->user_id,
                'key' => $this->idempotencyKey($order),
            ]);

            if ($response->status() === 404) {
                return null;
            }

            return $response->successful() ? $response->json() : null;
        } catch (\Throwable $e) {
            Log::debug('Shadow fetch failed: '.$e->getMessage());

            return null;
        }
    }

    private function create(Order $order): ?array
    {
        $payload = [
            'userId' => $order->user_id,
            'restaurantId' => $order->restaurant_id,
            'cityId' => $order->city_id,
            'channel' => $order->channel ?? 'app',
            'lines' => $order->items->map(fn ($item) => [
                'menuItemId' => $item->menu_item_id,
                'variantId' => $item->variant_snapshot['id'] ?? null,
                'quantity' => (int) $item->quantity,
                'unitPrice' => $this->money($item->unit_price),
            ])->values()->all(),
            'pricing' => [
                'grandTotal' => $this->money($order->grand_total),
                'tax' => $this->money($order->tax_amount),
                'vendorDiscount' => $this->money($order->vendor_discount_amount),
                'deliveryFee' => $this->money($order->delivery_fee),
                'platformFee' => $this->money($order->platform_fee),
                'couponDiscount' => $this->money($order->coupon_discount_amount),
                'commission' => $this->money($order->platform_commission),
                'commissionGst' => '0.00',
                'netPayout' => $this->money($order->net_payout),
            ],
        ];

        try {
            $response = Http::timeout(5)
                ->acceptJson()
                ->withHeaders(['Idempotency-Key' => $this->idempotencyKey($order)])
                ->post($this->url('/api/orders'), $payload);

            if (! $response->successful()) {
                Log::warning('Shadow create rejected.', [
                    'order_id' => $order->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::warning('Shadow create failed: '.$e->getMessage(), ['order_id' => $order->id]);

            return null;
        }
    }

    private function syncStatus(Order $order, array $javaOrder): void
    {
        $target = OrderStatusMapper::toCommand($order->status);
        $current = $javaOrder['status'] ?? 'PENDING';

        if (! $target || $target === $current) {
            return;
        }

        // The shadow must never move backwards or reopen a terminal order.
        if (in_array($current, ['DELIVERED', 'CANCELLED', 'ABANDONED', 'RESCUED'], true)) {
            return;
        }

        $javaId = $javaOrder['id'] ?? null;
        if (! $javaId) {
            return;
        }

        try {
            if (in_array($target, ['CANCELLED', 'ABANDONED'], true)) {
                $this->patchStatus($order, $javaId, $target, $order->cancel_reason ?? 'shadow_cancel');

                return;
            }

            $path = OrderStatusMapper::pathTo($target);
            $currentPath = OrderStatusMapper::pathTo($current);
            $start = count($currentPath);

            foreach (array_slice($path, $start) as $step) {
                $this->patchStatus($order, $javaId, $step, null);
            }
        } catch (\Throwable $e) {
            Log::debug('Shadow status sync failed: '.$e->getMessage(), ['order_id' => $order->id]);
        }
    }

    private function patchStatus(Order $order, string $javaId, string $status, ?string $reason): void
    {
        $body = ['status' => strtolower($status)];

        if ($reason) {
            $body['reason'] = mb_substr($reason, 0, 40);
        }

        $response = Http::timeout(5)
            ->acceptJson()
            ->patch($this->url('/api/orders/'.$javaId.'/status'), $body);

        if ($response->failed()) {
            Log::debug('Shadow status patch failed.', [
                'order_id' => $order->id,
                'target' => $status,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }
    }

    private function money($value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function url(string $path): string
    {
        return rtrim((string) config('services.order_command.url'), '/').$path;
    }
}
