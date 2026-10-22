<?php

namespace App\Domain\Conversation\States;

use App\Domain\Conversation\WaContext;
use App\Domain\Conversation\WaMessage;
use App\Domain\Conversation\WaReply;

class AddressPickState implements WaState
{
    public function handle(WaContext $context, WaMessage $message): WaReply
    {
        $text = trim($message->text);

        // Format: line1 | landmark | pincode
        $parts = array_map('trim', explode('|', $text));

        $line1 = $parts[0] ?? '';
        $landmark = $parts[1] ?? '';
        $pincode = $parts[2] ?? '';

        if (mb_strlen($line1) < 5 || mb_strlen($line1) > 200) {
            return WaReply::text($message->waId, 'Address line 5-200 characters honi chahiye. Format: "Flat/Gali | Landmark | Pincode".');
        }

        if (mb_strlen($landmark) > 120) {
            return WaReply::text($message->waId, 'Landmark max 120 characters. Dobara bhejo:');
        }

        if (! preg_match('/^[1-9][0-9]{5}$/', $pincode)) {
            return WaReply::text($message->waId, 'Pincode 6-digit valid hona chahiye. Format: "Flat/Gali | Landmark | Pincode".');
        }

        $context->set('address_text', "{$line1}, {$landmark}");
        $context->set('pincode', $pincode);
        $context->transition(self::SHARE_LOCATION);

        return WaReply::buttons($message->waId, '✅ Address save. Ab restaurant dhoondo:', [
            ['id' => 'nav:near_me', 'title' => '📍 Aas-Paas'],
            ['id' => 'nav:search', 'title' => '🔍 Naam Se'],
        ]);
    }
}
