<?php

namespace App\Providers;

use App\Domain\Conversation\WaReply;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaCloudProvider implements WhatsAppProvider
{
    private string $apiBase;

    private string $version;

    private string $phoneNumberId;

    private string $token;

    public function __construct()
    {
        $this->apiBase = config('whatsapp.meta_api_base', 'https://graph.facebook.com');
        $this->version = config('whatsapp.meta_api_version', 'v22.0');
        $this->phoneNumberId = config('whatsapp.meta_phone_number_id');
        $this->token = config('whatsapp.meta_access_token');
    }

    private function endpoint(string $suffix = ''): string
    {
        return "{$this->apiBase}/{$this->version}/{$this->phoneNumberId}{$suffix}";
    }

    public function send(WaReply $reply): void
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $reply->waId,
            'type' => 'text',
            'text' => ['preview_url' => false, 'body' => $reply->text ?? ''],
        ];

        if ($reply->interactive) {
            $payload = array_merge($payload, [
                'type' => 'interactive',
                'interactive' => array_merge(
                    ['type' => 'button', 'body' => ['text' => $reply->text ?? '']],
                    ['action' => ['buttons' => array_map(
                        fn ($b) => ['type' => 'reply', 'reply' => ['id' => $b['id'], 'title' => $b['title']]],
                        $reply->interactive['buttons'] ?? [],
                    )]],
                ),
            ]);
            unset($payload['text']);
        }

        $this->post('messages', $payload);
    }

    public function sendTemplate(string $waId, string $templateName, array $components = []): void
    {
        $this->post('messages', [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $waId,
            'type' => 'template',
            'template' => array_merge(['name' => $templateName, 'language' => ['code' => 'en']], $components ? ['components' => $components] : []),
        ]);
    }

    public function markRead(string $waMessageId): void
    {
        if (! $waMessageId) {
            return;
        }

        $this->post('messages', [
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $waMessageId,
        ]);
    }

    private function post(string $path, array $payload): void
    {
        if (! $this->token || ! $this->phoneNumberId) {
            Log::warning('MetaCloudProvider not configured. Message skipped.', ['path' => $path]);

            return;
        }

        $response = Http::withToken($this->token)
            ->asJson()
            ->timeout(15)
            ->post($this->endpoint('/'.$path), $payload);

        if ($response->failed()) {
            Log::error('Meta API call failed.', [
                'path' => $path,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }
    }
}
