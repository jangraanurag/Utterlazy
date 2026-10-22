<?php

namespace App\Http\Controllers;

use App\Domain\Conversation\StateMachine;
use App\Domain\Conversation\WaMessage as DomainWaMessage;
use App\Models\WaMessage;
use App\Providers\WhatsAppProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController
{
    public function verify(Request $request): JsonResponse
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        $expected = config('whatsapp.meta_verify_token', 'utterlazy_wa_webhook');

        if ($mode === 'subscribe' && hash_equals($expected, (string) $token)) {
            return response()->json((int) $challenge);
        }

        return response()->json(['error' => 'Invalid verify token.'], 403);
    }

    public function receive(Request $request, StateMachine $stateMachine, WhatsAppProvider $provider): JsonResponse
    {
        // Signature verification (X-Hub-Signature-256) — mandatory in production.
        if (! $this->signatureValid($request)) {
            Log::warning('WA webhook: invalid signature.', ['ip' => $request->ip()]);

            return response()->json(['error' => 'Invalid signature.'], 401);
        }

        $entry = $request->input('entry.0', []);

        // Status updates (sent/read) — ack only.
        if (isset($entry['changes'][0]['value']['statuses'])) {
            return response()->json(['status' => 'ok']);
        }

        $message = DomainWaMessage::fromMetaPayload($entry);

        if (! $message || ! $message->waId) {
            return response()->json(['status' => 'no_message']);
        }

        // Dedupe — Meta may deliver a webhook more than once.
        if ($message->waMessageId && WaMessage::isDuplicate($message->waMessageId)) {
            return response()->json(['status' => 'duplicate']);
        }

        if ($message->waMessageId) {
            WaMessage::create([
                'wa_message_id' => $message->waMessageId,
                'wa_id' => $message->waId,
                'direction' => 'in',
                'payload' => $request->input('entry'),
            ]);
        }

        try {
            $reply = $stateMachine->handle($message);
            $provider->send($reply);

            WaMessage::create([
                'wa_message_id' => 'out-'.bin2hex(random_bytes(16)),
                'wa_id' => $message->waId,
                'direction' => 'out',
                'payload' => ['text' => $reply->text, 'interactive' => $reply->interactive, 'template' => $reply->template],
            ]);
        } catch (\Throwable $e) {
            Log::error('WA state machine failed.', ['wa_id' => $message->waId, 'error' => $e->getMessage()]);

            $provider->send(\App\Domain\Conversation\WaReply::text(
                $message->waId,
                'Kuch problem aa gayi. Support agent connect ho raha hai.'
            ));
        }

        return response()->json(['status' => 'ok']);
    }

    private function signatureValid(Request $request): bool
    {
        $secret = config('whatsapp.meta_app_secret');

        if (! $secret) {
            // Dev mode: no secret configured.
            return config('app.env') !== 'production';
        }

        $signature = $request->header('X-Hub-Signature-256', '');

        if (! str_starts_with($signature, 'sha256=')) {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals(substr($signature, 7), $expected);
    }
}
