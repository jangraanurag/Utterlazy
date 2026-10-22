<?php

namespace App\Domain\Conversation\States;

use App\Domain\Conversation\WaContext;
use App\Domain\Conversation\WaMessage;
use App\Domain\Conversation\WaReply;
use App\Models\WaContact;
use App\Services\PinGuard;

/**
 * First-time 4-digit order PIN setup (two entries). The PIN is stored hashed;
 * the first entry is kept only as a hash while the customer confirms it.
 */
class PinSetupState implements WaState
{
    public function handle(WaContext $context, WaMessage $message): WaReply
    {
        $pin = trim($message->text);

        if (! PinGuard::isValid($pin)) {
            return WaReply::text($message->waId, '4-digit PIN bhejo (sirf numbers, e.g. 4821).');
        }

        if (! $context->get('pin_first_hash')) {
            $context->set('pin_first_hash', PinGuard::hash($pin));

            return WaReply::text($message->waId, 'PIN dobara bhejo confirm karne ke liye.');
        }

        if (! PinGuard::verify($pin, (string) $context->get('pin_first_hash'))) {
            $context->set('pin_first_hash', null);

            return WaReply::text($message->waId, 'PIN match nahi hua. Phir se 4-digit PIN set karo:');
        }

        WaContact::updateOrCreate(
            ['wa_id' => $message->waId],
            ['order_pin_hash' => PinGuard::hash($pin), 'last_seen' => now()],
        );

        $context->set('pin_first_hash', null);
        $context->set('pin_verified', true);
        $context->set('pin_attempts', 0);
        $context->transition(WaState::CHECKOUT);

        return (new CheckoutState)->handle(
            $context,
            new WaMessage($message->waId, null, '', 'pay:'.($context->get('pending_payment_mode', 'cod'))),
        );
    }
}
