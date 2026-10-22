<?php

namespace App\Services\Refunds;

use App\Models\Order;
use App\Services\PaymentService;
use RuntimeException;

class RazorpayRefundGateway implements RefundGateway
{
    public function refund(Order $order, float $amount, array $notes = []): void
    {
        if (! $order->razorpay_payment_id) {
            throw new RuntimeException('missing_razorpay_payment_reference');
        }

        PaymentService::createRefund($order->razorpay_payment_id, $amount, $notes);
    }
}
