<?php

namespace App\Domain\Conversation\States;

use App\Domain\Conversation\WaContext;
use App\Domain\Conversation\WaMessage;
use App\Domain\Conversation\WaReply;
use App\Models\WaContact;
use App\Services\PinGuard;

/**
 * Confirms an order with the customer's 4-digit PIN. This is the human
 * confirmation gate for AI-composed / WhatsApp orders.
 */
class PinVerifyState implements WaState
{
    private const MAX_ATTEMPTS = 3;

    public function handle(WaContext $context, WaMessage $message): WaReply
    {
        $pin = trim($message->text);
        $contact = WaContact::find($message->waId);

        if (! PinGuard::isValid($pin)) {
            return WaReply::text($message->waId, '4-digit PIN bhejo (sirf numbers).');
        }

        if (! PinGuard::verify($pin, $contact?->order_pin_hash)) {
            $attempts = ((int) $context->get('pin_attempts', 0)) + 1;
            $context->set('pin_attempts', $attempts);

            if ($attempts >= self::MAX_ATTEMPTS) {
                $context->transition(WaState::HUMAN);

                return WaReply::text($message->waId, 'Bahut zyada galat PIN attempts. Support agent connect kar raha hai.');
            }

            return WaReply::text($message->waId, 'PIN galat hai. Dobara bhejo.');
        }

        $context->set('pin_verified', true);
        $context->set('pin_attempts', 0);
        $context->transition(WaState::CHECKOUT);

        return (new CheckoutState)->handle(
            $context,
            new WaMessage($message->waId, null, '', 'pay:'.($context->get('pending_payment_mode', 'cod'))),
        );
    }
}
