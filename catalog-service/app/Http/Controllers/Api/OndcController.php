<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Jobs\SendOndcCallback;
use App\Services\Ondc\OndcCatalogMapper;
use App\Services\Ondc\OndcOrderMapper;
use App\Services\Pricing\PricingParityService;
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
    public function __construct(
        private readonly OndcCatalogMapper $mapper,
        private readonly OndcOrderMapper $orders,
    ) {
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

        $signature = $this->verifySignature($request, $context);
        if ($signature !== null) {
            return $signature;
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

        $this->dispatchCallback($onSearch, $context);

        return response()->json([
            'context' => $onSearch['context'],
            'message' => ['ack' => ['status' => 'ACK']],
            'on_search' => $onSearch,
        ]);
    }

    public function select(Request $request)
    {
        if (! config('services.ondc.enabled')) {
            return $this->nack('ONDC_DISABLED', 'ONDC adapter is disabled.', 503);
        }

        $payload = $request->json()->all();
        $context = $payload['context'] ?? [];
        $missing = $this->missingContext($context, 'select');
        if ($missing !== []) {
            return $this->nack('INVALID_CONTEXT', 'Missing context: '.implode(', ', $missing), 422);
        }

        $signature = $this->verifySignature($request, $context);
        if ($signature !== null) {
            return $signature;
        }

        $order = $payload['message']['order'] ?? [];
        $providerId = $order['provider']['id'] ?? null;
        $requested = $order['items'] ?? [];

        if (! $providerId || ! is_array($requested) || $requested === []) {
            return $this->nack('INVALID_ORDER', 'provider.id and items are required.', 422);
        }

        $menuItems = MenuItem::whereIn('id', array_column($requested, 'id'))->get()->keyBy('id');
        $items = [];
        $subtotal = 0.0;

        foreach ($requested as $line) {
            $menuItem = $menuItems->get($line['id'] ?? '');
            $qty = max(1, min(20, (int) ($line['quantity'] ?? 1)));

            if (! $menuItem) {
                return $this->nack('ITEM_NOT_FOUND', 'Unknown item: '.($line['id'] ?? '?'), 422);
            }

            $price = (float) $menuItem->price;
            $subtotal += $price * $qty;
            $items[] = [
                'id' => $menuItem->id,
                'descriptor' => ['name' => $menuItem->name],
                'quantity' => ['count' => $qty],
                'price' => ['currency' => 'INR', 'value' => number_format($price, 2, '.', '')],
                'fulfillment_id' => 'F1',
            ];
        }

        $quote = app(PricingParityService::class)->calculate([
            'subtotal' => $subtotal,
            'vendor_discount_rate' => 0.0,
            'tax_rate' => 5.0,
            'delivery_min' => 10.0,
            'delivery_max' => 50.0,
            'delivery_estimate' => 27.0,
            'delivery_free_above' => 500.0,
            'platform_fee' => 0.0,
            'coupon' => 0.0,
            'tip' => 0.0,
            'commission_rate' => 0.0,
            'commission_gst_rate' => 0.0,
        ]);

        $onSelect = $this->orders->onSelect($context, ['id' => $providerId], $items, [
            'items' => (float) $quote['subtotal'],
            'tax' => (float) $quote['tax'],
            'delivery' => (float) $quote['delivery_fee'],
            'total' => (float) $quote['grand_total'],
        ], now()->toIso8601String(), $this->callbackMeta());

        $this->dispatchCallback($onSelect, $context);

        return response()->json([
            'context' => $onSelect['context'],
            'message' => ['ack' => ['status' => 'ACK']],
            'on_select' => $onSelect,
        ]);
    }

    public function init(Request $request)
    {
        if (! config('services.ondc.enabled')) {
            return $this->nack('ONDC_DISABLED', 'ONDC adapter is disabled.', 503);
        }

        $payload = $request->json()->all();
        $context = $payload['context'] ?? [];
        $missing = $this->missingContext($context, 'init');
        if ($missing !== []) {
            return $this->nack('INVALID_CONTEXT', 'Missing context: '.implode(', ', $missing), 422);
        }

        $signature = $this->verifySignature($request, $context);
        if ($signature !== null) {
            return $signature;
        }

        $order = $payload['message']['order'] ?? [];
        if (! isset($order['provider']['id'], $order['items'])) {
            return $this->nack('INVALID_ORDER', 'provider.id and items are required.', 422);
        }

        $onInit = $this->orders->onInit($context, $order, now()->toIso8601String(), $this->callbackMeta());
        $this->dispatchCallback($onInit, $context);

        return response()->json([
            'context' => $onInit['context'],
            'message' => ['ack' => ['status' => 'ACK']],
            'on_init' => $onInit,
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

    private function missingContext(array $context, string $expectedAction = 'search'): array
    {
        $required = ['domain', 'action', 'country', 'city', 'bap_id', 'bap_uri', 'transaction_id', 'message_id', 'timestamp'];
        $missing = [];

        foreach ($required as $key) {
            if (empty($context[$key])) {
                $missing[] = $key;
            }
        }

        if (($context['action'] ?? null) !== $expectedAction) {
            $missing[] = 'action('.$expectedAction.')';
        }

        return $missing;
    }

    private function verifySignature(Request $request, array $context): ?\Illuminate\Http\JsonResponse
    {
        if (! config('services.ondc.require_signature', true)) {
            return null;
        }

        $verifier = new OndcVerifier($this->registryKeys());
        $result = $verifier->verify((string) $request->header('Authorization', ''), $request->getContent());

        if (! $result['ok']) {
            Log::warning('ONDC signature rejected', [
                'transaction_id' => $context['transaction_id'] ?? null,
                'error' => $result['error'] ?? 'unknown',
            ]);

            return $this->nack('INVALID_SIGNATURE', 'Signature rejected: '.($result['error'] ?? 'unknown'), 401);
        }

        return null;
    }

    private function callbackMeta(): array
    {
        return [
            'bpp_id' => config('services.ondc.bpp_id'),
            'bpp_uri' => config('services.ondc.bpp_uri'),
        ];
    }

    private function dispatchCallback(array $payload, array $context): void
    {
        if (config('services.ondc.callback_enabled') && ! empty($context['bap_uri'])) {
            SendOndcCallback::dispatch($payload, (string) $context['bap_uri']);
        }
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
