<?php

namespace App\Services\Ondc;

/**
 * Maps internal catalog data to the Beckn `on_search` shape.
 *
 * Pure mapper: the controller supplies restaurants/items and the timestamp so
 * this can be tested without HTTP or a database.
 */
class OndcCatalogMapper
{
    /**
     * @param array<string, mixed> $context inbound search context
     * @param array<int, array<string, mixed>> $providers normalized providers
     * @return array<string, mixed>
     */
    public function onSearch(array $context, array $providers, string $timestamp, array $meta = []): array
    {
        $baseContext = array_merge($context, [
            'action' => 'on_search',
            'bpp_id' => $meta['bpp_id'] ?? ($context['bpp_id'] ?? null),
            'bpp_uri' => $meta['bpp_uri'] ?? ($context['bpp_uri'] ?? null),
            'timestamp' => $timestamp,
        ]);

        return [
            'context' => $baseContext,
            'message' => [
                'catalog' => [
                    'bpp/descriptor' => [
                        'name' => $meta['bpp_name'] ?? 'UtterLazy',
                    ],
                    'bpp/providers' => array_values($providers),
                    'bpp/fulfillments' => [
                        [
                            'id' => 'F1',
                            'type' => 'Delivery',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $restaurant
     * @param array<int, array<string, mixed>> $items
     * @return array<string, mixed>
     */
    public function provider(array $restaurant, array $items): array
    {
        return [
            'id' => (string) ($restaurant['id'] ?? ''),
            'descriptor' => [
                'name' => (string) ($restaurant['name'] ?? 'Restaurant'),
            ],
            'locations' => [
                [
                    'id' => 'L-'.($restaurant['id'] ?? ''),
                    'gps' => $this->gps($restaurant['latitude'] ?? null, $restaurant['longitude'] ?? null),
                ],
            ],
            'items' => array_values(array_map(fn (array $item) => $this->item($item), $items)),
            'fulfillments' => [
                [
                    'id' => 'F1',
                    'type' => 'Delivery',
                    'tracking' => true,
                ],
            ],
        ];
    }

    private function item(array $item): array
    {
        return [
            'id' => (string) ($item['id'] ?? ''),
            'descriptor' => [
                'name' => (string) ($item['name'] ?? 'Item'),
            ],
            'price' => [
                'currency' => 'INR',
                'value' => number_format((float) ($item['price'] ?? 0), 2, '.', ''),
            ],
            'category_id' => $item['category_id'] ?? null,
            'fulfillment_id' => 'F1',
            'time' => [
                'label' => 'enable',
                'duration' => 'PT30M',
            ],
            'tags' => array_key_exists('is_veg', $item)
                ? [['code' => 'veg', 'value' => ((bool) $item['is_veg']) ? 'yes' : 'no']]
                : [],
        ];
    }

    private function gps($lat, $lng): string
    {
        if ($lat === null || $lng === null) {
            return '';
        }

        return number_format((float) $lat, 6, '.', '').','.number_format((float) $lng, 6, '.', '');
    }
}
