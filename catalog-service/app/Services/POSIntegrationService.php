<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Restaurant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class POSIntegrationService
{
    // Supported POS platforms and their base URL patterns
    protected array $platforms = [
        'petpooja' => [
            'name' => 'Petpooja',
            'base_url' => null, // configured per restaurant
            'auth_type' => 'api_key',
        ],
        'posist' => [
            'name' => 'Posist',
            'base_url' => null,
            'auth_type' => 'bearer',
        ],
        'limetray' => [
            'name' => 'Limetray',
            'base_url' => null,
            'auth_type' => 'api_key',
        ],
        'inresto' => [
            'name' => 'Inresto',
            'base_url' => null,
            'auth_type' => 'bearer',
        ],
        'dotpe' => [
            'name' => 'DotPe',
            'base_url' => null,
            'auth_type' => 'api_key',
        ],
    ];

    /**
     * Push order to the restaurant's POS system.
     */
    public function pushOrder(Order $order): bool
    {
        $restaurant = $order->restaurant;
        if (! $restaurant || ! $restaurant->pos_integration) {
            return false; // POS not configured
        }

        $posConfig = json_decode($restaurant->pos_integration, true);
        $platform = $posConfig['platform'] ?? null;
        $apiKey = $posConfig['api_key'] ?? $posConfig['api_secret'] ?? null;
        $endpoint = $posConfig['endpoint'] ?? null;

        if (! $platform || ! $apiKey || ! $endpoint) {
            Log::warning("POS: Incomplete config for restaurant {$restaurant->id}");

            return false;
        }

        $order->load('items.menuItem', 'user', 'address');

        $payload = $this->buildPayload($platform, $order);

        try {
            $response = Http::timeout(10)->withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => $posConfig['auth_type'] === 'bearer' ? "Bearer {$apiKey}" : null,
                'X-API-Key' => $posConfig['auth_type'] === 'api_key' ? $apiKey : null,
            ])->post($endpoint, $payload);

            if ($response->successful()) {
                $order->update([
                    'pos_sync_status' => 'synced',
                    'pos_synced_at' => now(),
                    'pos_order_id' => $response->json('order_id') ?? $response->json('id'),
                ]);
                Log::info("POS: Order {$order->id} pushed to {$platform}");

                return true;
            }

            Log::error("POS: Failed for order {$order->id}", [
                'platform' => $platform,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Exception $e) {
            Log::error("POS: Exception for order {$order->id}: {$e->getMessage()}");
        }

        $order->update(['pos_sync_status' => 'failed']);

        return false;
    }

    /**
     * Pull menu from POS system.
     */
    public function pullMenu(Restaurant $restaurant): ?array
    {
        if (! $restaurant->pos_integration) {
            return null;
        }

        $posConfig = json_decode($restaurant->pos_integration, true);
        $platform = $posConfig['platform'] ?? null;
        $apiKey = $posConfig['api_key'] ?? null;
        $endpoint = $posConfig['menu_endpoint'] ?? null;

        if (! $platform || ! $apiKey || ! $endpoint) {
            return null;
        }

        try {
            $response = Http::timeout(30)->withHeaders([
                'Authorization' => $posConfig['auth_type'] === 'bearer' ? "Bearer {$apiKey}" : null,
                'X-API-Key' => $posConfig['auth_type'] === 'api_key' ? $apiKey : null,
            ])->get($endpoint);

            if ($response->successful()) {
                $items = $this->parseMenuResponse($platform, $response->json());

                return $items;
            }
        } catch (\Exception $e) {
            Log::error("POS menu pull failed: {$e->getMessage()}");
        }

        return null;
    }

    /**
     * Build platform-specific order payload.
     */
    protected function buildPayload(string $platform, Order $order): array
    {
        $base = [
            'order_id' => $order->id,
            'order_type' => 'delivery',
            'source' => 'UtterLazy',
            'customer_name' => $order->user?->name ?? 'Customer',
            'customer_phone' => $order->user?->phone ?? '',
            'delivery_address' => $order->address?->flat_building.', '.$order->address?->street ?? '',
            'payment_mode' => $order->payment_mode,
            'order_total' => $order->grand_total,
            'items' => $order->items->map(fn ($item) => [
                'name' => $item->menuItem?->name ?? 'Item',
                'quantity' => $item->quantity,
                'price' => $item->unit_price,
                'total' => $item->total_price,
                'instructions' => $item->special_instructions ?? '',
            ])->toArray(),
        ];

        return match ($platform) {
            'petpooja' => array_merge($base, ['api_version' => 'v2', 'source_id' => 'utterlazy']),
            'posist' => ['data' => ['type' => 'order', 'attributes' => $base]],
            'limetray' => $base,
            'inresto' => $base,
            'dotpe' => $base,
            default => $base,
        };
    }

    /**
     * Parse POS menu response into UtterLazy format.
     */
    protected function parseMenuResponse(string $platform, ?array $data): array
    {
        if (empty($data)) {
            return [];
        }

        $items = [];
        $rawItems = match ($platform) {
            'petpooja' => $data['data']['items'] ?? $data['items'] ?? [],
            'posist' => $data['data']['attributes']['items'] ?? $data['items'] ?? [],
            default => $data['items'] ?? $data['menu_items'] ?? [],
        };

        foreach ($rawItems as $raw) {
            $items[] = [
                'name' => $raw['name'] ?? $raw['item_name'] ?? '',
                'price' => $raw['price'] ?? $raw['selling_price'] ?? 0,
                'category' => $raw['category'] ?? $raw['category_name'] ?? 'Uncategorized',
                'is_available' => $raw['is_available'] ?? $raw['status'] === 'active',
                'description' => $raw['description'] ?? null,
                'is_veg' => $raw['is_veg'] ?? $raw['type'] === 'veg',
                'hsn_code' => $raw['hsn_code'] ?? null,
                'gst_percent' => $raw['gst_percent'] ?? $raw['tax'] ?? 0,
            ];
        }

        return $items;
    }
}
