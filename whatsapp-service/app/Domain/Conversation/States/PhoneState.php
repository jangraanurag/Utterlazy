<?php

namespace App\Domain\Conversation\States;

use App\Domain\Conversation\WaContext;
use App\Domain\Conversation\WaMessage;
use App\Domain\Conversation\WaReply;
use App\Services\CatalogClient;
use App\Services\PhoneNormalizer;

class PhoneState implements WaState
{
    private const CONFIRM = ['yes', 'y', 'haan', 'ha', 'ok', 'okay', 'हाँ', 'हां'];

    public function handle(WaContext $context, WaMessage $message): WaReply
    {
        $text = trim($message->text);
        $waPhone = PhoneNormalizer::toLocal($message->waId);

        $phone = in_array(mb_strtolower($text), self::CONFIRM, true)
            ? $waPhone
            : PhoneNormalizer::toLocal($text);

        if (! $phone) {
            return WaReply::text(
                $message->waId,
                "10-digit mobile bhejo (6-9 se shuru), ya YES likho agar isi WhatsApp number se link karna hai."
            );
        }

        $result = app(CatalogClient::class)->requestOtp($phone);

        if ($result['status'] !== 200) {
            return WaReply::text(
                $message->waId,
                'OTP bhej nahi paye. Thodi der baad try karo, ya "agent" likho.'
            );
        }

        $context->set('pending_phone', $phone);
        $context->set('otp_attempts', 0);
        $context->transition(WaState::OTP);

        return WaReply::text(
            $message->waId,
            "📲 OTP bheja gaya {$phone} par.\n6-digit code bhejo (5 min valid). Dobara chahiye toh \"resend\" likho."
        );
    }
}
