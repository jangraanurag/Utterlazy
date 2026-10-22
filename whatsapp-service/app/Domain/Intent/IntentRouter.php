<?php

namespace App\Domain\Intent;

class IntentRouter
{
    public const SEARCH = 'search';
    public const ADD_ITEM = 'add_item';
    public const TRACK = 'track';
    public const CANCEL_REQUEST = 'cancel_request';
    public const COMPLAINT = 'complaint';
    public const HUMAN = 'human';
    public const NONE = 'none';

    /**
     * Route free-form text to an intent.
     *
     * The AI layer (Gemini tool schema) may refine this routing. Confidence
     * below WA_INTENT_CONFIDENCE_THRESHOLD falls back to button menus in the
     * state handlers — the AI is NEVER allowed to create or cancel an order.
     */
    public function resolve(string $text, ?string $buttonPayload = null): array
    {
        if ($buttonPayload) {
            $decoded = explode(':', $buttonPayload, 3);

            return ['intent' => $decoded[0] ?? self::NONE, 'arg' => $decoded[1] ?? null, 'payload' => $decoded[2] ?? null];
        }

        $lower = mb_strtolower(trim($text));

        return match (true) {
            preg_match('/^(hi|hello|namaste|hey|start|menu|order|hello!?)$/', $lower) === 1 => ['intent' => self::NONE],
            str_contains($lower, 'track'), str_contains($lower, 'order status'), str_contains($lower, 'where is') => ['intent' => self::TRACK],
            str_contains($lower, 'cancel') => ['intent' => self::CANCEL_REQUEST],
            str_contains($lower, 'complaint'), str_contains($lower, 'problem'), str_contains($lower, 'missing') => ['intent' => self::COMPLAINT],
            str_contains($lower, 'agent'), str_contains($lower, 'human'), str_contains($lower, 'help') => ['intent' => self::HUMAN],
            str_contains($lower, 'near me'), str_contains($lower, 'restaurant'), str_contains($lower, 'food'), str_contains($lower, 'khaana') => ['intent' => self::SEARCH],
            preg_match('/^\d{1,3}$/', $lower) === 1 => ['intent' => self::ADD_ITEM, 'arg' => $lower],
            default => ['intent' => self::NONE],
        };
    }
}
