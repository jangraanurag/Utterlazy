<?php

namespace App\Domain\Conversation\States;

use App\Domain\Conversation\WaContext;
use App\Domain\Conversation\WaMessage;
use App\Domain\Conversation\WaReply;
use App\Models\WaConsent;
use App\Models\WaContact;
use App\Services\CatalogClient;
use App\Services\WaTokenStore;

class OtpState implements WaState
{
    private const MAX_ATTEMPTS = 5;

    public function handle(WaContext $context, WaMessage $message): WaReply
    {
        $text = trim($message->text);
        $phone = $context->get('pending_phone');

        if (! $phone) {
            $context->transition(WaState::PHONE);

            return WaReply::text($message->waId, 'Phone number dobara bhejo:');
        }

        if (mb_strtolower($text) === 'resend') {
            app(CatalogClient::class)->requestOtp($phone);

            return WaReply::text($message->waId, "📲 Naya OTP bheja gaya {$phone} par.");
        }

        if (! preg_match('/^[0-9]{6}$/', $text)) {
            return WaReply::text($message->waId, '6-digit OTP bhejo (sirf digits).');
        }

        $attempts = ((int) $context->get('otp_attempts', 0)) + 1;

        if ($attempts > self::MAX_ATTEMPTS) {
            $context->set('handoff_reason', 'otp_attempts_exceeded');
            $context->transition(WaState::HUMAN);

            return WaReply::text($message->waId, 'Bahut zyada attempts. Support agent connect kar raha hai.');
        }

        $context->set('otp_attempts', $attempts);

        $result = app(CatalogClient::class)->verifyOtp($phone, $text);

        if ($result['status'] !== 200) {
            return WaReply::text(
                $message->waId,
                'OTP galat ya expire ho gaya. Dobara bhejo, ya "resend" likho.'
            );
        }

        $body = $result['body'];
        $token = $body['token'] ?? null;
        $userId = $body['user']['id'] ?? null;

        if (! $token || ! $userId) {
            return WaReply::text($message->waId, 'Account link nahi ho paya. "agent" likho.');
        }

        app(WaTokenStore::class)->put($message->waId, $token);

        WaContact::updateOrCreate(
            ['wa_id' => $message->waId],
            ['user_id' => $userId, 'last_seen' => now()],
        );

        $consent = WaConsent::firstOrNew(['wa_id' => $message->waId]);
        $consent->utility = true;
        $consent->save();

        $after = $context->get('after_link', 'checkout');

        if ($after === 'track') {
            $context->transition(WaState::TRACK);

            return WaReply::text($message->waId, '✅ Account linked. Order ID bhejo, status bata deta hoon:');
        }

        $context->transition(WaState::CHECKOUT);

        return WaReply::buttons(
            $message->waId,
            "✅ Account linked!\n\nPayment kaise karna hai?",
            [
                ['id' => 'pay:cod', 'title' => '💵 Cash on Delivery'],
                ['id' => 'pay:prepaid', 'title' => '📲 Pay Online'],
            ],
        );
    }
}
