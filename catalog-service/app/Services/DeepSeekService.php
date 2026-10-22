<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeepSeekService
{
    protected $apiKey;

    protected $baseUrl = 'https://api.deepseek.com/v1/chat/completions';

    public function __construct()
    {
        $this->apiKey = config('services.deepseek.key');
    }

    public function chat(string $message, string $systemPrompt = 'You are a helpful assistant for UtterLazy food delivery app.', float $temperature = 0.3): string
    {
        if (empty($this->apiKey)) {
            return $this->fallback();
        }

        try {
            $response = Http::timeout(15)->withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl, [
                'model' => 'deepseek-chat',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $message],
                ],
                'temperature' => $temperature,
                'max_tokens' => 1024,
            ]);

            if ($response->successful()) {
                return $response->json('choices.0.message.content') ?? $this->fallback();
            }

            Log::error('DeepSeek API error: '.$response->body());
        } catch (\Exception $e) {
            Log::error('DeepSeek Exception: '.$e->getMessage());
        }

        return $this->fallback();
    }

    public function parseMenuFromPhoto(string $base64Image, string $mimeType): string
    {
        $systemPrompt = 'You are a menu digitizer for UtterLazy food delivery app. Extract all items, prices, and categories from the menu photo. Return valid JSON only.';

        $message = 'Extract all menu items from this photo. Return JSON with categories array containing items with: name, name_english (if non-english), price, is_veg, description, spice_level.';

        return $this->chat($message, $systemPrompt, 0.1);
    }

    protected function fallback(): string
    {
        return '{}';
    }
}
