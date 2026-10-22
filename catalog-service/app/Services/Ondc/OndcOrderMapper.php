<?php

namespace App\Services\Ondc;

/**
 * Builds Beckn on_select / on_init payloads. No order is created here.
 */
class OndcOrderMapper
{
    /**
     * @param array<string,mixed> $context
     * @param array<string,mixed> $provider
     * @param array<int,array<string,mixed>> $items
     * @param array{items:float,tax:float,delivery:float,total:float} $quote
     * @return array<string,mixed>
     */
    public function onSelect(array $context, array $provider, array $items, array $quote, string $timestamp, array $meta = []): array
    {
        return [
            'context' => $this->context($context, 'on_select', $timestamp, $meta),
            'message' => [
                'order' => [
                    'provider' => ['id' => (string) ($provider['id'] ?? '')],
                    'items' => array_values($items),
                    'quote' => [
                        'price' => ['currency' => 'INR', 'value' => $this->money($quote['total'])],
                        'breakup' => [
                            ['title' => 'Items', 'price' => ['currency' => 'INR', 'value' => $this->money($quote['items'])]],
                            ['title' => 'Tax', 'price' => ['currency' => 'INR', 'value' => $this->money($quote['tax'])]],
                            ['title' => 'Delivery', 'price' => ['currency' => 'INR', 'value' => $this->money($quote['delivery'])]],
                        ],
                    ],
                    'fulfillments' => [[
                        'id' => 'F1',
                        'type' => 'Delivery',
                        'tracking' => true,
                    ]],
                ],
            ],
        ];
    }

    /**
     * @param array<string,mixed> $context
     * @param array<string,mixed> $order
     * @return array<string,mixed>
     */
    public function onInit(array $context, array $order, string $timestamp, array $meta = [], string $paymentType = 'ON-ORDER'): array
    {
        return [
            'context' => $this->context($context, 'on_init', $timestamp, $meta),
            'message' => [
                'order' => array_merge($order, [
                    'payment' => [
                        'type' => $paymentType,
                        'collected_by' => 'BPP',
                        'status' => 'NOT-PAID',
                    ],
                    'fulfillments' => [[
                        'id' => 'F1',
                        'type' => 'Delivery',
                        'tracking' => true,
                        'state' => ['descriptor' => ['code' => 'Pending']],
                    ]],
                ]),
            ],
        ];
    }

    private function context(array $context, string $action, string $timestamp, array $meta): array
    {
        return array_merge($context, [
            'action' => $action,
            'bpp_id' => $meta['bpp_id'] ?? ($context['bpp_id'] ?? null),
            'bpp_uri' => $meta['bpp_uri'] ?? ($context['bpp_uri'] ?? null),
            'timestamp' => $timestamp,
        ]);
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
