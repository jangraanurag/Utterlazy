<?php

namespace App\Services\Refunds;

use App\Models\Order;

interface RefundGateway
{
    /**
     * Send a refund to the payment provider.
     *
     * @throws \RuntimeException when the provider rejects or the reference is missing
     */
    public function refund(Order $order, float $amount, array $notes = []): void;
}
