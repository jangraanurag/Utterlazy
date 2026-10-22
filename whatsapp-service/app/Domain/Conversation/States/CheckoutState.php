<?php

namespace App\Domain\Conversation\States;

use App\Domain\Conversation\WaContext;
use App\Domain\Conversation\WaMessage;
use App\Domain\Conversation\WaReply;
use App\Models\WaCart;
use App\Models\WaContact;
use App\Services\CatalogClient;
use Illuminate\Support\Facades\Log;

class CheckoutState implements WaState
{
    public function handle(WaContext $context, WaMessage $message): WaReply
    {
        $payload = $message->buttonPayload;
        $paymentMode = $payload === 'pay:prepaid' ? 'prepaid' : 'cod';

        $cart = WaCart::where('wa_id', $message->waId)->first();
        if (! $cart || empty($cart->lines)) {
            $context->transition(self::SHARE_LOCATION);

            return WaReply::text($message->waId, 'Cart khaali ho gaya. Dobara add karo.');
        }

        $userToken = app(\App\Services\WaTokenStore::class)->get($message->waId);

        if (! $userToken) {
            $context->set('after_link', 'checkout');
            $context->transition(self::PHONE);

            return WaReply::text(
                $message->waId,
                "Checkout ke liye account link karna hoga.\nApna 10-digit mobile bhejo, ya YES likho agar isi WhatsApp number se link karna hai."
            );
        }

        if (! $context->get('pin_verified', false)) {
            $contact = WaContact::updateOrCreate(
                ['wa_id' => $message->waId],
                ['last_seen' => now()],
            );

            $context->set('pending_payment_mode', $paymentMode);
            $context->set('pin_attempts', 0);

            if (! $contact->order_pin_hash) {
                $context->transition(WaState::PIN_SETUP);

                return WaReply::text(
                    $message->waId,
                    "Order confirm karne ke liye 4-digit PIN set karo.\nPehli baar: PIN bhejo, phir dobara bhejo confirm karne ke liye."
                );
            }

            $context->transition(WaState::PIN_VERIFY);

            return WaReply::text($message->waId, 'Order confirm karne ke liye apna 4-digit order PIN bhejo:');
        }

        $context->set('pin_verified', false);

        if (count($cart->lines) > 50) {
            return WaReply::text($message->waId, 'Max 50 items ho sakte hain.');
        }

        $items = collect($cart->lines)->map(fn ($line) => [
            'menu_item_id' => $line['menu_item_id'],
            'quantity' => $line['quantity'],
            'variant_id' => $line['variant_id'] ?? null,
            'add_on_item_ids' => $line['add_on_item_ids'] ?? [],
            'special_instructions' => $line['special_instructions'] ?? null,
        ])->values()->all();

        $checkoutPayload = [
            'restaurant_id' => $cart->restaurant_id,
            'items' => $items,
            'payment_mode' => $paymentMode,
            'delivery_address_text' => $context->get('address_text'),
            'delivery_address_lat' => $context->get('latitude'),
            'delivery_address_lng' => $context->get('longitude'),
            'customer_tip' => (float) $context->get('tip', 0),
        ];

        $idempotencyKey = CatalogClient::idempotencyKeyFor($message->waId, (string) $context->session->id);

        try {
            $result = app(CatalogClient::class)->checkout($userToken, $checkoutPayload, $idempotencyKey);
        } catch (\Throwable $e) {
            Log::error('WA checkout failed.', ['wa_id' => $message->waId, 'error' => $e->getMessage()]);

            return WaReply::text($message->waId, 'Order place nahi ho paya. Thodi der baad try karo.');
        }

        if ($result['status'] === 422 || $result['status'] === 400) {
            return WaReply::text($message->waId, 'Order mein problem: '.($result['body']['error'] ?? 'validation failed').'. Cart dobara dekho.');
        }

        if ($result['status'] >= 500) {
            return WaReply::text($message->waId, 'Server busy hai. Agent connect kar raha hai.');
        }

        $order = $result['body']['order'] ?? [];
        $orderId = $order['id'] ?? null;

        $context->set('order_id', $orderId);
        $context->transition(self::PAYMENT);

        if ($paymentMode === 'cod') {
            return WaReply::text(
                $message->waId,
                "✅ *Order placed (COD)*\nOrder: {$orderId}\n\nDelivery OTP order status messages mein milega. Track: \"track\" likho."
            );
        }

        $payLink = rtrim((string) config('whatsapp.payment_link_base'), '/').'/'.$orderId;

        return WaReply::text(
            $message->waId,
            "✅ *Order placed — payment pending*\nOrder: {$orderId}\n\n💳 Pay securely: {$payLink}\n\nPayment ke baad status update milega. Track: \"track\" likho."
        );
    }
}
