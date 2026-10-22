<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\PricingParityCheck;
use App\Models\SystemConfig;
use App\Services\OrderCommandRouter;
use App\Services\Pricing\PricingParityComparator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Certification job: asks the Java pricing engine to price a real order and
 * compares it with what Laravel charged. Writes nothing to the order; never
 * blocks checkout. Only runs for allowlisted cities/restaurants.
 */
class CheckPricingParity implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 5;

    public bool $afterCommit = true;

    public function __construct(public string $orderId)
    {
    }

    public function handle(OrderCommandRouter $router, PricingParityComparator $comparator): void
    {
        if (! config('services.order_command.cutover_enabled')) {
            return;
        }

        $order = Order::with(['items', 'restaurant'])->find($this->orderId);

        if (! $order) {
            return;
        }

        if (! $router->inCutoverScope($order->city_id, $order->restaurant_id)) {
            return;
        }

        $expected = $this->laravelPricing($order);
        $request = $this->preflightPayload($order);

        try {
            $response = Http::timeout(5)
                ->acceptJson()
                ->post(rtrim((string) config('services.order_command.url'), '/').'/api/pricing/preflight', $request);

            if ($response->failed()) {
                $this->record($order, $expected, [], ['http_status' => $response->status()], false);

                return;
            }

            $java = $response->json() ?? [];
            $diffs = $comparator->compare($expected, $java);
            $this->record($order, $expected, $java, $diffs, empty($diffs));
        } catch (\Throwable $e) {
            $this->record($order, $expected, [], ['error' => $e->getMessage()], false);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::warning('Pricing parity check failed to run.', [
            'order_id' => $this->orderId,
            'error' => $e->getMessage(),
        ]);
    }

    /** @return array<string, string> */
    private function laravelPricing(Order $order): array
    {
        return [
            'grandTotal' => $this->money($order->grand_total),
            'tax' => $this->money($order->tax_amount),
            'vendorDiscount' => $this->money($order->vendor_discount_amount),
            'deliveryFee' => $this->money($order->delivery_fee),
            'platformFee' => $this->money($order->platform_fee),
            'couponDiscount' => $this->money($order->coupon_discount_amount),
            'commission' => $this->money($order->platform_commission),
            'netPayout' => $this->money($order->net_payout),
        ];
    }

    private function preflightPayload(Order $order): array
    {
        $restaurant = $order->restaurant;

        $taxRate = $restaurant && ! empty($restaurant->gst_number)
            ? (float) (SystemConfig::where('config_key', 'platform_tax_rate')->value('config_value') ?? 5)
            : 0.0;

        $deliveryMin = (float) (SystemConfig::where('config_key', 'delivery_fee_min')->value('config_value') ?? 10);
        $deliveryMax = (float) (SystemConfig::where('config_key', 'delivery_fee_max')->value('config_value') ?? 50);
        $freeAbove = (float) (SystemConfig::where('config_key', 'delivery_fee_free_above')->value('config_value') ?? 500);
        $basePay = (float) (SystemConfig::where('config_key', 'rider_base_pay')->value('config_value') ?? 15);
        $perKm = (float) (SystemConfig::where('config_key', 'rider_per_km_rate')->value('config_value') ?? 4);
        $platformFee = (float) (SystemConfig::where('config_key', 'platform_fee')->value('config_value') ?? 5);

        return [
            'lines' => $order->items->map(fn ($item) => [
                'menuItemId' => $item->menu_item_id,
                'variantId' => $item->variant_snapshot['id'] ?? null,
                'quantity' => (int) $item->quantity,
                'unitPrice' => $this->money($item->unit_price),
            ])->values()->all(),
            'vendorDiscountRate' => '5.00',
            'taxRate' => $this->money($taxRate),
            'deliveryMin' => $this->money($deliveryMin),
            'deliveryMax' => $this->money($deliveryMax),
            // Checkout uses `base + 3km × perKm` as the delivery estimate.
            'deliveryEstimate' => $this->money($basePay + (3 * $perKm)),
            'deliveryFreeAbove' => $this->money($freeAbove),
            'platformFee' => $this->money($platformFee),
            'coupon' => $this->money($order->coupon_discount_amount),
            'tip' => $this->money($order->customer_tip),
            'commissionRate' => '15.00',
            'commissionGstRate' => '18.00',
        ];
    }

    private function record(Order $order, array $expected, array $java, array $diffs, bool $matched): void
    {
        PricingParityCheck::create([
            'order_id' => $order->id,
            'city_id' => $order->city_id,
            'restaurant_id' => $order->restaurant_id,
            'matched' => $matched,
            'diffs' => $diffs ?: null,
            'laravel' => $expected ?: null,
            'java' => $java ?: null,
        ]);

        if (! $matched) {
            Log::warning('Pricing parity mismatch.', [
                'order_id' => $order->id,
                'diffs' => $diffs,
            ]);
        }
    }

    private function money($value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
