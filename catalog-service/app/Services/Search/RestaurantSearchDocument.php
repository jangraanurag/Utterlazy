<?php

namespace App\Services\Search;

class RestaurantSearchDocument
{
    /**
     * @param array<string,mixed> $restaurant
     * @return array<string,mixed>
     */
    public static function build(array $restaurant): array
    {
        $lat = isset($restaurant['latitude']) ? (float) $restaurant['latitude'] : null;
        $lng = isset($restaurant['longitude']) ? (float) $restaurant['longitude'] : null;

        return [
            'id' => (string) ($restaurant['id'] ?? ''),
            'name' => (string) ($restaurant['name'] ?? ''),
            'cuisine_type' => $restaurant['cuisine_type'] ?? null,
            'rating' => (float) ($restaurant['rating'] ?? 0),
            'city_id' => $restaurant['city_id'] ?? null,
            'is_active' => (bool) ($restaurant['is_active'] ?? true),
            'is_accepting_orders' => (bool) ($restaurant['is_accepting_orders'] ?? false),
            'location' => ($lat !== null && $lng !== null) ? ['lat' => $lat, 'lon' => $lng] : null,
        ];
    }
}
