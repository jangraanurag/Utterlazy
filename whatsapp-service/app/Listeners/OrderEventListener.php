<?php

namespace App\Listeners;

use App\Models\WaConsent;
use App\Models\WaContact;
use App\Providers\WhatsAppProvider;
use Illuminate\Support\Facades\Log;

class OrderEventListener
{
    public function __construct(private readonly WhatsAppProvider $provider)
    {
    }

    public const TEMPLATES = [
        'pending' => 'order_status_update',
        'confirmed' => 'order_status_update',
        'preparing' => 'order_status_update',
        'ready_for_pickup' => 'order_status_update',
        'out_for_delivery' => 'order_status_update',
        'delivered' => 'order_delivered',
        'cancelled' => 'order_cancelled',
        'abandoned' => 'order_cancelled',
        'rescued' => 'order_status_update',
    ];

    /**
     * Handle an order/payment event envelope.
     * Utility messages only — marketing consent is irrelevant here.
     */
    public function handle(array $envelope): void
    {
        $data = $envelope['data'] ?? [];
        $orderId = $data['order_id'] ?? null;
        $userId = $data['user_id'] ?? null;

        if (! $orderId || ! $userId) {
            return;
        }

        $contact = WaContact::where('user_id', $userId)->first();

        if (! $contact) {
            return; // Customer does not use WhatsApp.
        }

        if (! WaConsent::canSendUtility($contact->wa_id)) {
            return;
        }

        $eventType = $envelope['event_type'] ?? '';
        $status = $data['status'] ?? $data['payment_status'] ?? null;

        $template = $eventType === 'payment.status_changed'
            ? 'payment_received'
            : (self::TEMPLATES[$status] ?? null);

        if (! $template) {
            return;
        }

        try {
            $this->provider->sendTemplate($contact->wa_id, $template, [
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => (string) $orderId],
                        ['type' => 'text', 'text' => (string) ($status ?? 'updated')],
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Order status template failed.', [
                'wa_id' => $contact->wa_id,
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
