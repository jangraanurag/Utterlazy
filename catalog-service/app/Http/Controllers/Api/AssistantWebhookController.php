<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\VoiceAssistantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AssistantWebhookController extends Controller
{
    protected VoiceAssistantService $assistant;

    public function __construct(VoiceAssistantService $assistant)
    {
        $this->assistant = $assistant;
    }

    /**
     * Dialogflow fulfillment webhook (Google Assistant, Actions on Google).
     */
    public function dialogflow(Request $request)
    {
        $body = $request->all();
        $queryText = $body['queryResult']['queryText'] ?? '';
        $parameters = $body['queryResult']['parameters'] ?? [];
        $session = $body['session'] ?? '';

        $userEmail = $parameters['email'] ?? $body['originalDetectIntentRequest']['payload']['user']['email'] ?? null;
        $user = $userEmail ? User::where('email', $userEmail)->first() : null;

        $intent = $this->assistant->parseIntent($queryText, $user ? ['user_id' => $user->id] : []);
        $result = $this->assistant->executeIntent($intent, $user);

        $intentName = $body['queryResult']['intent']['displayName'] ?? 'Default Welcome Intent';
        $responseText = $result['response_text'] ?? 'How can I help you?';

        Log::info('Dialogflow webhook', [
            'intent' => $intentName,
            'query' => $queryText,
            'response' => $responseText,
        ]);

        return response()->json([
            'fulfillmentText' => $responseText,
            'fulfillmentMessages' => [
                ['text' => ['text' => [$responseText]]],
            ],
            'payload' => [
                'google' => [
                    'expectUserResponse' => true,
                    'richResponse' => [
                        'items' => [
                            ['simpleResponse' => ['textToSpeech' => $responseText]],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Alexa skill webhook.
     */
    public function alexa(Request $request)
    {
        $body = $request->all();
        $requestType = $body['request']['type'] ?? '';
        $intentName = $body['request']['intent']['name'] ?? '';

        if ($requestType === 'LaunchRequest') {
            return response()->json($this->alexaResponse(
                'Welcome to UtterLazy Food Delivery. You can say, order chicken biryani, or track my order. How can I help you?',
                false
            ));
        }

        if ($requestType === 'SessionEndedRequest') {
            return response()->json($this->alexaResponse('Goodbye!', true));
        }

        $slots = $body['request']['intent']['slots'] ?? [];
        $queryText = '';
        foreach ($slots as $slot) {
            if (! empty($slot['value'])) {
                $queryText .= $slot['value'].' ';
            }
        }
        $queryText = trim($queryText) ?: $intentName;

        $userAlexaId = $body['session']['user']['userId'] ?? null;
        $user = $userAlexaId ? User::where('alexa_user_id', $userAlexaId)->first() : null;

        $intent = $this->assistant->parseIntent($queryText, $user ? ['user_id' => $user->id] : []);
        $result = $this->assistant->executeIntent($intent, $user);

        return response()->json($this->alexaResponse(
            $result['response_text'] ?? 'How can I help you?'
        ));
    }

    protected function alexaResponse(string $text, bool $endSession = false): array
    {
        return [
            'version' => '1.0',
            'response' => [
                'outputSpeech' => [
                    'type' => 'PlainText',
                    'text' => $text,
                ],
                'shouldEndSession' => $endSession,
            ],
        ];
    }

    /**
     * Generic voice assistant API (for Gemini, Meta AI, or any custom assistant).
     * POST /api/assistant/chat
     */
    public function chat(Request $request)
    {
        $validated = $request->validate([
            'text' => 'required|string|max:1000',
            'user_id' => 'sometimes|exists:users,id',
            'lat' => 'sometimes|numeric',
            'lng' => 'sometimes|numeric',
            'platform' => 'sometimes|string', // gemini, meta, custom
        ]);

        $user = $validated['user_id'] ? User::find($validated['user_id']) : null;
        $location = [
            'lat' => $validated['lat'] ?? null,
            'lng' => $validated['lng'] ?? null,
        ];

        $intent = $this->assistant->parseIntent($validated['text'], $user ? ['user_id' => $user->id] : []);
        $result = $this->assistant->executeIntent($intent, $user, $location);

        return response()->json([
            'success' => $result['success'] ?? false,
            'response_text' => $result['response_text'] ?? 'How can I help?',
            'data' => $result['data'] ?? null,
            'intent' => $intent['action'] ?? 'unknown',
        ]);
    }

    /**
     * Link a voice assistant account to the user.
     * POST /api/assistant/link
     */
    public function link(Request $request)
    {
        $validated = $request->validate([
            'platform' => 'required|in:alexa,google_assistant',
            'platform_user_id' => 'required|string',
        ]);

        $user = $request->user();
        $field = $validated['platform'] === 'alexa' ? 'alexa_user_id' : 'google_assistant_id';

        // Check if this platform ID is already linked to another user
        $existing = User::where($field, $validated['platform_user_id'])->first();
        if ($existing && $existing->id !== $user->id) {
            return response()->json(['error' => 'This account is already linked to another user.'], 409);
        }

        $user->update([$field => $validated['platform_user_id']]);

        return response()->json([
            'message' => "Your {$validated['platform']} account has been linked! You can now use voice commands to order food.",
            'platform' => $validated['platform'],
        ]);
    }

    /**
     * Unlink a voice assistant account.
     * DELETE /api/assistant/link
     */
    public function unlink(Request $request)
    {
        $validated = $request->validate([
            'platform' => 'required|in:alexa,google_assistant',
        ]);

        $user = $request->user();
        $field = $validated['platform'] === 'alexa' ? 'alexa_user_id' : 'google_assistant_id';
        $user->update([$field => null]);

        return response()->json([
            'message' => "Your {$validated['platform']} account has been unlinked.",
        ]);
    }
}
