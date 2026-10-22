<?php

namespace App\Services;

use App\Models\Order;
use App\Models\WalletTransaction;
use App\Services\Refunds\RefundGateway;
use Illuminate\Support\Facades\Log;

class OrderReversalService
{
    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_REFUND_PENDING = 'refund_pending';

    public const MAX_RETRY_ATTEMPTS = 10;

    /**
     * Reverse a platform/restaurant-cancelled order.
     *
     * Formula reversal, no human approval: the customer never pays for a
     * failure they did not cause. COD orders are simply released.
     * Idempotent: a paid→refunded transition happens once.
     */
    public static function reverse(Order $order, string $cancelReason, ?RefundGateway $gateway = null): bool
    {
        if ($order->payment_status !== 'paid') {
            $order->update(['cancel_reason' => $cancelReason]);

            return false;
        }

        if ($order->payment_mode === 'wallet') {
            $order->getConnection()->transaction(function () use ($order, $cancelReason) {
                $order->user?->increment('wallet_balance', (float) $order->grand_total);

                WalletTransaction::create([
                    'user_id' => $order->user_id,
                    'amount' => (float) $order->grand_total,
                    'type' => 'credit',
                    'description' => "Refund for cancelled Order #{$order->id}",
                ]);

                $order->update([
                    'payment_status' => self::STATUS_REFUNDED,
                    'cancel_reason' => $cancelReason,
                    'refund_last_error' => null,
                ]);
            });

            return true;
        }

        $gateway ??= self::gateway();

        try {
            $gateway->refund($order, (float) $order->grand_total, [
                'order_id' => $order->id,
                'reason' => $cancelReason,
            ]);

            $order->update([
                'payment_status' => self::STATUS_REFUNDED,
                'cancel_reason' => $cancelReason,
                'refund_retried_at' => now(),
                'refund_last_error' => null,
            ]);

            return true;
        } catch (\Throwable $e) {
            self::markPending($order, $cancelReason, $e);

            return false;
        }
    }

    /**
     * Retry a refund that previously failed at the gateway (or had no payment
     * reference). Called by the scheduled job and by finance ops.
     */
    public static function retryPending(Order $order, ?RefundGateway $gateway = null): bool
    {
        if ($order->payment_status !== self::STATUS_REFUND_PENDING) {
            return false;
        }

        if ((int) $order->refund_attempts >= self::MAX_RETRY_ATTEMPTS) {
            return false;
        }

        $gateway ??= self::gateway();

        try {
            $gateway->refund($order, (float) $order->grand_total, [
                'order_id' => $order->id,
                'reason' => $order->cancel_reason ?? 'refund_retry',
            ]);

            $order->update([
                'payment_status' => self::STATUS_REFUNDED,
                'refund_retried_at' => now(),
                'refund_last_error' => null,
            ]);

            return true;
        } catch (\Throwable $e) {
            self::markPending($order, $order->cancel_reason ?? 'refund_retry', $e);

            return false;
        }
    }

    private static function markPending(Order $order, string $reason, \Throwable $e): void
    {
        $order->update([
            'payment_status' => self::STATUS_REFUND_PENDING,
            'cancel_reason' => $reason,
            'refund_attempts' => ((int) $order->refund_attempts) + 1,
            'refund_last_error' => mb_substr($e->getMessage(), 0, 1000),
            'refund_retried_at' => now(),
        ]);

        try {
            Log::error('Refund failed; order marked refund_pending.', [
                'order_id' => $order->id,
                'attempt' => $order->refund_attempts,
                'error' => $e->getMessage(),
            ]);
        } catch (\Throwable) {
            // standalone tests without a facade root
        }
    }

    private static function gateway(): RefundGateway
    {
        return app(RefundGateway::class);
    }
}
