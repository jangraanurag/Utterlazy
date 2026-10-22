<?php

namespace App\Http\Controllers\Api;

use App\Events\NewOrderForVendor;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    /**
     * Create a payment order (returns Razorpay order ID)
     */
    public function createOrder(Request $request, Order $order)
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        if ($order->payment_status === 'paid') {
            return response()->json(['error' => 'Order is already paid.'], 422);
        }

        $amount = (float) $order->grand_total;
        if ($amount <= 0) {
            return response()->json(['error' => 'Invalid order amount.'], 422);
        }

        $razorpayOrder = PaymentService::createRazorpayOrder(
            $order->id,
            $amount
        );

        // Store the razorpay_order_id on the order for webhook matching
        $order->updateQuietly(['razorpay_order_id' => $razorpayOrder['id']]);

        return response()->json([
            'razorpay_order' => $razorpayOrder,
            'key_id' => config('services.razorpay.key_id'),
            'amount' => $amount,
            'currency' => 'INR',
        ]);
    }

    /**
     * Verify payment after Razorpay client-side callback
     */
    public function verify(Request $request, Order $order)
    {
        $validated = $request->validate([
            'razorpay_order_id' => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature' => 'required|string',
        ]);

        if ($order->payment_status === 'paid') {
            return response()->json(['message' => 'Payment already verified.', 'order' => $order->fresh()]);
        }

        $result = PaymentService::verifyPayment(
            $validated['razorpay_order_id'],
            $validated['razorpay_payment_id'],
            $validated['razorpay_signature']
        );

        if ($result['verified']) {
            $order->update([
                'payment_mode' => 'prepaid',
                'payment_status' => 'paid',
                'paid_at' => now(),
                'razorpay_payment_id' => $validated['razorpay_payment_id'],
                'razorpay_order_id' => $validated['razorpay_order_id'],
                'delivery_otp' => $order->delivery_otp ?? str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT),
            ]);

            // Notify vendor that order is confirmed and paid
            event(new NewOrderForVendor($order, $order->restaurant_id));

            return response()->json([
                'message' => 'Payment successful!',
                'order' => $order->fresh(),
            ]);
        }

        return response()->json(['error' => 'Payment verification failed.'], 422);
    }

    /**
     * Razorpay Webhook: server-side payment confirmation
     */
    public function webhook(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Razorpay-Signature');

        if (! $signature) {
            Log::warning('Razorpay webhook: missing signature');

            return response()->json(['error' => 'Missing signature'], 400);
        }

        if (! PaymentService::verifyWebhookSignature($payload, $signature)) {
            Log::warning('Razorpay webhook: invalid signature');

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $event = $request->input('event');
        $paymentEntity = $request->input('payload.payment.entity', []);

        // Failed payment: release the order, never notify the kitchen, no charge.
        if ($event === 'payment.failed' && ! empty($paymentEntity['order_id'])) {
            $order = Order::where('razorpay_order_id', $paymentEntity['order_id'])->first();

            if ($order && $order->payment_status !== 'paid' && $order->status === 'pending') {
                $order->update([
                    'status' => 'cancelled',
                    'cancel_reason' => 'payment_failed',
                ]);
            }

            return response()->json(['status' => 'ok']);
        }

        if ($event === 'payment.captured' && ! empty($paymentEntity['id'])) {
            $razorpayPaymentId = $paymentEntity['id'];
            $razorpayOrderId = $paymentEntity['order_id'] ?? null;
            $amountPaid = ($paymentEntity['amount'] ?? 0) / 100;

            if (! $razorpayOrderId) {
                Log::warning('Razorpay webhook: missing order_id in payment entity', $paymentEntity);

                return response()->json(['error' => 'Missing order_id'], 422);
            }

            $order = Order::where('razorpay_order_id', $razorpayOrderId)->first();

            if (! $order) {
                Log::warning('Razorpay webhook: order not found', ['razorpay_order_id' => $razorpayOrderId]);

                return response()->json(['error' => 'Order not found'], 404);
            }

            if ($order->payment_status === 'paid') {
                return response()->json(['message' => 'Already processed']);
            }

            // Verify amount matches
            if (abs((float) $order->grand_total - $amountPaid) > 0.50) {
                Log::error('Razorpay webhook: amount mismatch', [
                    'order_id' => $order->id,
                    'expected' => $order->grand_total,
                    'received' => $amountPaid,
                ]);

                return response()->json(['error' => 'Amount mismatch'], 422);
            }

            $order->update([
                'payment_mode' => 'prepaid',
                'payment_status' => 'paid',
                'paid_at' => now(),
                'razorpay_payment_id' => $razorpayPaymentId,
                'delivery_otp' => $order->delivery_otp ?? str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT),
            ]);

            event(new NewOrderForVendor($order, $order->restaurant_id));

            Log::info('Razorpay webhook: payment captured', [
                'order_id' => $order->id,
                'payment_id' => $razorpayPaymentId,
            ]);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Process refund (admin only via auth middleware)
     */
    public function refund(Request $request, Order $order)
    {
        if ($order->user_id !== $request->user()->id && ! ($request->user()->is_admin ?? false)) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        if (! $order->razorpay_payment_id) {
            return response()->json(['error' => 'No payment to refund.'], 422);
        }

        if ($order->payment_status === 'refunded') {
            return response()->json(['error' => 'Payment already refunded.'], 422);
        }

        $result = PaymentService::createRefund(
            $order->razorpay_payment_id,
            $order->grand_total
        );

        $order->update(['payment_status' => 'refunded']);

        return response()->json([
            'message' => 'Refund initiated',
            'refund' => $result,
        ]);
    }
}
