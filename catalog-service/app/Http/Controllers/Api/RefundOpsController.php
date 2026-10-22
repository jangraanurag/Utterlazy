<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\AuditLogService;
use App\Services\OrderReversalService;
use Illuminate\Http\Request;

class RefundOpsController extends Controller
{
    /**
     * Refunds stuck at the gateway or missing a payment reference.
     */
    public function index(Request $request)
    {
        $orders = Order::where('payment_status', OrderReversalService::STATUS_REFUND_PENDING)
            ->when($request->boolean('exhausted'), fn ($q) => $q->where('refund_attempts', '>=', OrderReversalService::MAX_RETRY_ATTEMPTS))
            ->orderBy('refund_retried_at')
            ->orderByDesc('created_at')
            ->paginate((int) $request->get('per_page', 25));

        return response()->json($orders);
    }

    public function retry(Request $request, Order $order)
    {
        if ($order->payment_status !== OrderReversalService::STATUS_REFUND_PENDING) {
            return response()->json(['error' => 'Order is not awaiting a refund retry.'], 422);
        }

        if ((int) $order->refund_attempts >= OrderReversalService::MAX_RETRY_ATTEMPTS) {
            return response()->json([
                'error' => 'Retry limit reached. Finance must resolve this refund manually.',
                'attempts' => (int) $order->refund_attempts,
            ], 422);
        }

        $ok = OrderReversalService::retryPending($order);

        AuditLogService::log(
            action: 'refund.retry',
            module: 'Finance',
            entityType: 'order',
            entityId: $order->id,
            newValues: [
                'success' => $ok,
                'attempts' => (int) $order->fresh()->refund_attempts,
                'error' => $order->fresh()->refund_last_error,
            ],
        );

        return response()->json([
            'refunded' => $ok,
            'order' => $order->fresh()->only([
                'id', 'payment_status', 'refund_attempts', 'refund_last_error', 'refund_retried_at',
            ]),
        ], $ok ? 200 : 409);
    }
}
