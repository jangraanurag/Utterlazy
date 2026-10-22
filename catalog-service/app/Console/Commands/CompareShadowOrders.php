<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\OrderCommandShadowClient;
use App\Support\OrderStatusMapper;
use Illuminate\Console\Command;

class CompareShadowOrders extends Command
{
    protected $signature = 'order-command:compare
                            {--hours=24 : Compare orders created in this window}
                            {--limit=500 : Max orders to compare}
                            {--show=20 : Max mismatch lines to print}
                            {--fail-on-mismatch : Exit non-zero on any mismatch or missing mirror}';

    protected $description = 'Compare Laravel orders with the Wave 2 shadow order-command service';

    public function handle(OrderCommandShadowClient $client): int
    {
        if (! $client->enabled()) {
            $this->error('Shadow mode is off. Set ORDER_COMMAND_SHADOW_ENABLED=true and ORDER_COMMAND_URL.');

            return self::FAILURE;
        }

        $orders = Order::with('items')
            ->where('created_at', '>=', now()->subHours((int) $this->option('hours')))
            ->orderByDesc('created_at')
            ->limit((int) $this->option('limit'))
            ->get();

        $compared = 0;
        $matched = 0;
        $missing = 0;
        $mismatched = 0;
        $shown = 0;

        foreach ($orders as $order) {
            $java = $client->fetch($order);

            if ($java === null) {
                $missing++;
                $this->warnIfShown("missing shadow order for #{$order->id}");

                continue;
            }

            $compared++;
            $diffs = $this->diff($order, $java);

            if (empty($diffs)) {
                $matched++;

                continue;
            }

            $mismatched++;
            foreach ($diffs as $diff) {
                $this->warnIfShown("#{$order->id}: {$diff}");
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Shadow parity: compared=%d matched=%d mismatched=%d missing=%d (window=%dh)',
            $compared, $matched, $mismatched, $missing, (int) $this->option('hours'),
        ));

        $failed = $mismatched > 0 || $missing > 0;

        return $failed && $this->option('fail-on-mismatch') ? self::FAILURE : self::SUCCESS;
    }

    private int $shownCount = 0;

    private function warnIfShown(string $line): void
    {
        $show = (int) $this->option('show');

        if ($show > 0 && $this->shownCount < $show) {
            $this->warn($line);
            $this->shownCount++;
        }
    }

    /**
     * @return string[] human-readable differences
     */
    private function diff(Order $order, array $java): array
    {
        $diffs = [];

        $expectedStatus = OrderStatusMapper::toCommand($order->status);
        if ($expectedStatus !== ($java['status'] ?? null)) {
            $diffs[] = "status laravel={$order->status}/{$expectedStatus} java=".($java['status'] ?? 'null');
        }

        $expectedChannel = strtolower((string) ($order->channel ?? 'app'));
        $actualChannel = strtolower((string) ($java['channel'] ?? ''));
        if ($expectedChannel !== $actualChannel) {
            $diffs[] = "channel laravel={$expectedChannel} java={$actualChannel}";
        }

        $expectedTotal = number_format((float) $order->grand_total, 2, '.', '');
        $actualTotal = number_format((float) ($java['grandTotal'] ?? 0), 2, '.', '');
        if ($expectedTotal !== $actualTotal) {
            $diffs[] = "total laravel={$order->grand_total} java=".($java['grandTotal'] ?? 'null');
        }

        foreach ([
            'tax' => 'tax_amount',
            'vendorDiscount' => 'vendor_discount_amount',
            'deliveryFee' => 'delivery_fee',
            'platformFee' => 'platform_fee',
            'couponDiscount' => 'coupon_discount_amount',
            'commission' => 'platform_commission',
            'netPayout' => 'net_payout',
        ] as $javaKey => $column) {
            $expected = number_format((float) ($order->{$column} ?? 0), 2, '.', '');
            $actual = number_format((float) ($java[$javaKey] ?? 0), 2, '.', '');

            if ($expected !== $actual) {
                $diffs[] = "{$column} laravel={$expected} java={$actual}";
            }
        }

        $expectedLines = $order->items
            ->groupBy('menu_item_id')
            ->map(fn ($items) => (int) $items->sum('quantity'))
            ->sortKeys()
            ->all();

        $actualLines = collect($java['lines'] ?? [])
            ->groupBy('menuItemId')
            ->map(fn ($lines) => (int) $lines->sum('quantity'))
            ->sortKeys()
            ->all();

        if ($expectedLines !== $actualLines) {
            $diffs[] = 'lines laravel='.json_encode($expectedLines).' java='.json_encode($actualLines);
        }

        return $diffs;
    }
}
