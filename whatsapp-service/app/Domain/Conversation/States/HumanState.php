<?php

namespace App\Domain\Conversation\States;

use App\Domain\Conversation\WaContext;
use App\Domain\Conversation\WaMessage;
use App\Domain\Conversation\WaReply;
use App\Models\WaHandoff;
use App\Services\EventPublisher;

class HumanState implements WaState
{
    public function handle(WaContext $context, WaMessage $message): WaReply
    {
        $reason = mb_substr(trim($message->text) ?: 'Customer requested support', 0, 300);
        $orderId = $context->get('order_id');

        $handoff = WaHandoff::openFor($message->waId);

        if (! $handoff) {
            $handoff = WaHandoff::create([
                'wa_id' => $message->waId,
                'order_id' => $orderId,
                'reason' => $reason,
                'status' => 'open',
            ]);
        }

        app(EventPublisher::class)->publish('support.ticket.v1', [
            'event_type' => 'support.ticket_opened',
            'wa_id' => $message->waId,
            'order_id' => $orderId,
            'handoff_id' => $handoff->id,
            'reason' => $reason,
        ]);

        return WaReply::text(
            $message->waId,
            "🙋 Support agent connect ho raha hai. Kripya thoda intezaar karein — ticket number: #".strtoupper(substr($handoff->id, 0, 8))
        );
    }
}
