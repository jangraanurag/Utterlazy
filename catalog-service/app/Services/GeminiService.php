<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected $apiKey;

    protected $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key');
    }

    /**
     * Generate a personalized greeting for the user.
     */
    public function generateGreeting($userName, $context = [])
    {
        $timeOfDay = date('H');
        $greetingType = $timeOfDay < 12 ? 'morning' : ($timeOfDay < 17 ? 'afternoon' : 'evening');

        $prompt = "Generate a short, premium, and appetizing greeting for a food delivery app user named {$userName}. 
                   Context: It is currently {$greetingType}. ".($context['weather'] ?? 'clear skies').'.
                   Keep it under 15 words. Tone: Luxurious, editorial, welcoming.';

        return $this->callGemini($prompt, "Good {$greetingType}, {$userName}. Ready for a feast?");
    }

    /**
     * Predict ETA using AI based on multiple factors.
     */
    public function predictEta($distanceKm, $prepTimeMins, $riderProximity)
    {
        $prompt = "Predict a realistic delivery time (ETA) in minutes.
                   Distance: {$distanceKm} km.
                   Restaurant Prep Time: {$prepTimeMins} mins.
                   Rider Proximity to Restaurant: {$riderProximity} mins.
                   Consider urban traffic. Return ONLY the number (integer).";

        $prediction = $this->callGemini($prompt, ($prepTimeMins + ($distanceKm * 5) + $riderProximity));

        return (int) preg_replace('/[^0-9]/', '', $prediction);
    }

    public function callGemini($prompt, $fallback = null)
    {
        if (empty($this->apiKey) || $this->apiKey === 'your_gemini_key_here') {
            return $fallback;
        }

        try {
            $response = Http::post("{$this->baseUrl}?key={$this->apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return $data['candidates'][0]['content']['parts'][0]['text'] ?? $fallback;
            }

            Log::error('Gemini Error: '.$response->body());

            return $fallback;
        } catch (\Exception $e) {
            Log::error('Gemini Exception: '.$e->getMessage());

            return $fallback;
        }
    }
}
