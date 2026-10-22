<?php

namespace App\Domain\Conversation;

class WaMessage
{
    public function __construct(
        public readonly string $waId,
        public readonly ?string $waMessageId,
        public readonly string $text,
        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null,
        public readonly ?string $buttonPayload = null,
    ) {
    }

    public static function fromMetaPayload(array $entry): ?self
    {
        $changes = $entry['changes'] ?? [];
        foreach ($changes as $change) {
            $value = $change['value'] ?? [];
            $messages = $value['messages'] ?? [];
            foreach ($messages as $message) {
                if (($message['type'] ?? null) === 'text') {
                    return new self(
                        waId: $value['contacts'][0]['wa_id'] ?? '',
                        waMessageId: $message['id'] ?? null,
                        text: $message['text']['body'] ?? '',
                    );
                }

                if (($message['type'] ?? null) === 'location') {
                    return new self(
                        waId: $value['contacts'][0]['wa_id'] ?? '',
                        waMessageId: $message['id'] ?? null,
                        text: '',
                        latitude: (float) ($message['location']['latitude'] ?? 0),
                        longitude: (float) ($message['location']['longitude'] ?? 0),
                    );
                }

                if (($message['type'] ?? null) === 'interactive') {
                    $interactive = $message['interactive'] ?? [];

                    return new self(
                        waId: $value['contacts'][0]['wa_id'] ?? '',
                        waMessageId: $message['id'] ?? null,
                        text: $interactive['button_reply']['title'] ?? $interactive['list_reply']['title'] ?? '',
                        buttonPayload: $interactive['button_reply']['id'] ?? $interactive['list_reply']['id'] ?? null,
                    );
                }
            }
        }

        return null;
    }
}
