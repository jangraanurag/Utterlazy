<?php

namespace App\Domain\Conversation\States;

use App\Domain\Conversation\WaContext;
use App\Domain\Conversation\WaMessage;
use App\Domain\Conversation\WaReply;
use App\Services\CatalogClient;

class NameSearchState implements WaState
{
    public function handle(WaContext $context, WaMessage $message): WaReply
    {
        $query = trim($message->text);

        if (mb_strlen($query) < 2 || mb_strlen($query) > 60) {
            return WaReply::text($message->waId, 'Naam 2 se 60 characters ka hona chahiye. Dobara likho:');
        }

        $results = app(CatalogClient::class)->searchByName($query, 5);
        $data = $results['data'] ?? $results;

        if (empty($data)) {
            return WaReply::text($message->waId, "\"{$query}\" ke liye kuch nahi mila. Koi aur naam try karo:");
        }

        $lines = ['🔍 *Search results (organic):*'];
        foreach (array_values($data) as $i => $restaurant) {
            $name = $restaurant['name'] ?? 'Restaurant';
            $lines[] = ($i + 1).". {$name}";
        }

        $context->set('list', $data);
        $context->transition(self::NEAR_ME);

        return WaReply::text($message->waId, implode("\n", $lines)."\n\nMenu ke liye number bhejo (1-5):");
    }
}
