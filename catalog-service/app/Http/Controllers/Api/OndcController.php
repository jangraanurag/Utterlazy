<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Jobs\SendOndcCallback;
use App\Services\Ondc\OndcCatalogMapper;
use App\Services\Ondc\OndcVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * ONDC seller-side protocol core.
 *
 * `search` validates the signed request and returns an `on_search` payload for
 * the integration harness. Production must POST it back to `bap_uri` as a signed
 * callback (outbound callback transport is intentionally not enabled yet).
 *
 * `confirm` is deliberately NACKed: order creation over ONDC requires payment,
 * fulfilment and cancellation parity that the platform has not certified.
 */
class OndcController extends Controller
{
    public function __construct(private readonly OndcCatalogMapper $mapper)
    {
    }

    public function search(Request $request)
    {
        if (! config('services.ondc.enabled')) {
            return $this->nack('ONDC_DISABLED', 'ONDC adapter is disabled.', 503);
        }

        $payload = $request->json()->all();
        $context = $payload['context'] ?? [];

        $missing = $this->missingContext($context);
        if ($missing !== []) {
            return $this->nack('INVALID_CONTEXT', 'Missing context: '.implode(', ', $missing), 422);
        }

        if (config('services.ondc.require_signature', true)) {
            $verifier = new OndcVerifier($this->registryKeys());
            $result = $verifier->verify((string) $request->header('Authorization', ''), $request->getContent());

            if (! $result['ok']) {
                Log::warning('ONDC search signature rejected', [
                    'transaction_id' => $context['transaction_id'] ?? null,
                    'error' => $result['error'] ?? 'unknown',
                ]);

                return $this->nack('INVALID_SIGNATURE', 'Signature rejected: '.($result['error'] ?? 'unknown'), 401);
            }
        }

        $intent = $payload['message']['intent'] ?? [];
        $query = data_get($intent, 'item.descriptor.name') ?? data_get($intent, 'category.id');

        $restaurants = Restaurant::query()
            ->where('is_active', true)
            ->when($query, fn ($q, $term) => $q->where(function ($w) use ($term) {
                $w->where('name', 'like', '%'.$term.'%')
                    ->orWhere('cuisine_type', 'like', '%'.$term.'%');
            }))
            ->orderByDesc('rating')
            ->limit(10)
            ->get(['id', 'name', 'latitude', 'longitude']);

        $providers = $restaurants->map(function (Restaurant $restaurant) {
            $items = MenuItem::where('restaurant_id', $restaurant->id)
                ->where('is_available', true)
                ->orderBy('name')
                ->limit(20)
                ->get(['id', 'name', 'price', 'category_id', 'global_category_id'])
                ->map(fn (MenuItem $item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'price' => $item->price,
                    'category_id' => $item->global_category_id ?? $item->category_id,
                ])
                ->all();

            return $this->mapper->provider([
                'id' => $restaurant->id,
                'name' => $restaurant->name,
                'latitude' => $restaurant->latitude,
                'longitude' => $restaurant->longitude,
            ], $items);
        })->all();

        $onSearch = $this->mapper->onSearch($context, $providers, now()->toIso8601String(), [
            'bpp_id' => config('services.ondc.bpp_id'),
            'bpp_uri' => config('services.ondc.bpp_uri'),
        ]);

        if (config('services.ondc.callback_enabled') && ! empty($context['bap_uri'])) {
            SendOndcCallback::dispatch($onSearch, (string) $context['bap_uri']);
        }

        return response()->json([
            'context' => $onSearch['context'],
            'message' => ['ack' => ['status' => 'ACK']],
            'on_search' => $onSearch,
        ]);
    }

    public function confirm(Request $request)
    {
        if (! config('services.ondc.enabled')) {
            return $this->nack('ONDC_DISABLED', 'ONDC adapter is disabled.', 503);
        }

        return $this->nack(
            'ONDC_CONFIRM_NOT_ENABLED',
            'Order creation over ONDC is not enabled. Payment/fulfilment parity is required first.',
            501,
        );
    }

    private function missingContext(array $context): array
    {
        $required = ['domain', 'action', 'country', 'city', 'bap_id', 'bap_uri', 'transaction_id', 'message_id', 'timestamp'];
        $missing = [];

        foreach ($required as $key) {
            if (empty($context[$key])) {
                $missing[] = $key;
            }
        }

        if (($context['action'] ?? null) !== 'search') {
            $missing[] = 'action(search)';
        }

        return $missing;
    }

    /** @return array<string, string> */
    private function registryKeys(): array
    {
        $raw = config('services.ondc.registry_keys', '');
        $decoded = is_string($raw) ? json_decode($raw, true) : [];

        return is_array($decoded) ? $decoded : [];
    }

    private function nack(string $code, string $message, int $status)
    {
        return response()->json([
            'message' => ['ack' => ['status' => 'NACK']],
            'error' => ['code' => $code, 'message' => $message],
        ], $status);
    }
}
