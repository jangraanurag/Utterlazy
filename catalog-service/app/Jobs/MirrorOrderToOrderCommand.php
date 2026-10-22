<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\OrderCommandShadowClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Mirrors an order (create + current status) into the Wave 2 shadow service.
 * Runs after commit; never blocks or alters the customer path.
 */
class MirrorOrderToOrderCommand implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 5;

    public bool $afterCommit = true;

    public function __construct(public string $orderId)
    {
    }

    public function handle(OrderCommandShadowClient $client): void
    {
        if (! $client->enabled()) {
            return;
        }

        $order = Order::with('items')->find($this->orderId);

        if (! $order) {
            return;
        }

        $client->mirror($order);
    }

    public function failed(\Throwable $e): void
    {
        Log::warning('Shadow mirror job failed.', [
            'order_id' => $this->orderId,
            'error' => $e->getMessage(),
        ]);
    }
}
