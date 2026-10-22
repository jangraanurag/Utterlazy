<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    private static function validateConfig(): array
    {
        $keyId = config('services.razorpay.key_id');
        $keySecret = config('services.razorpay.key_secret');

        if (empty($keyId) || empty($keySecret) || str_starts_with($keyId, 'mock_')) {
            Log::critical('Razorpay not configured or mock mode detected in production');
            throw new \RuntimeException('Payment gateway is not configured. Contact support.');
        }

        return [$keyId, $keySecret];
    }

    public static function createRazorpayOrder($orderId, $amount, $currency = 'INR')
    {
        [$keyId, $keySecret] = self::validateConfig();

        try {
            $response = Http::withBasicAuth($keyId, $keySecret)
                ->post('https://api.razorpay.com/v1/orders', [
                    'amount' => (int) round($amount * 100),
                    'currency' => $currency,
                    'receipt' => (string) $orderId,
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Razorpay Order Creation Failed', [
                'order_id' => $orderId,
                'response' => $response->body(),
            ]);
            throw new \Exception('Razorpay order creation failed: '.($response->json('error.description') ?? $response->body()));
        } catch (\Exception $e) {
            Log::error('Razorpay Order Creation Exception: '.$e->getMessage());
            throw $e;
        }
    }

    public static function verifyPayment($razorpayOrderId, $paymentId, $signature)
    {
        [$keyId, $keySecret] = self::validateConfig();

        $expectedSignature = hash_hmac('sha256', $razorpayOrderId.'|'.$paymentId, $keySecret);
        $verified = hash_equals($expectedSignature, $signature);

        return [
            'verified' => $verified,
            'payment_id' => $paymentId,
            'status' => $verified ? 'captured' : 'failed',
        ];
    }

    public static function createRefund($paymentId, $amount, $notes = [])
    {
        [$keyId, $keySecret] = self::validateConfig();

        try {
            $response = Http::withBasicAuth($keyId, $keySecret)
                ->post("https://api.razorpay.com/v1/payments/{$paymentId}/refund", [
                    'amount' => (int) round($amount * 100),
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Razorpay Refund Failed', [
                'payment_id' => $paymentId,
                'response' => $response->body(),
            ]);
            throw new \Exception('Refund failed: '.($response->json('error.description') ?? $response->body()));
        } catch (\Exception $e) {
            Log::error('Razorpay Refund Exception: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Verify Razorpay webhook signature
     */
    public static function verifyWebhookSignature(string $payload, string $signature): bool
    {
        [$keyId, $keySecret] = self::validateConfig();
        $expectedSignature = hash_hmac('sha256', $payload, $keySecret);

        return hash_equals($expectedSignature, $signature);
    }
}
