<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NavigationService
{
    protected $olaApiKey;

    public function __construct()
    {
        $this->olaApiKey = config('services.ola_maps.key') ?? env('OLA_MAPS_API_KEY');
    }

    /**
     * Get navigation route between two points.
     */
    public function getRoute(float $originLat, float $originLng, float $destLat, float $destLng): array
    {
        if ($this->olaApiKey) {
            return $this->olaMapsRoute($originLat, $originLng, $destLat, $destLng);
        }

        return $this->fallbackRoute($originLat, $originLng, $destLat, $destLng);
    }

    /**
     * Generate a navigation URL that can be opened in Ola Maps.
     */
    public function getNavigationUrl(float $destLat, float $destLng, string $label = 'Destination'): string
    {
        // Ola Maps deep link
        $olaUrl = "https://ola Maps.app/?daddr={$destLat},{$destLng}&dname=".urlencode($label);

        // Google Maps fallback
        $gmapUrl = "https://www.google.com/maps/dir/?api=1&destination={$destLat},{$destLng}";

        return $olaUrl;
    }

    /**
     * Get step-by-step navigation instructions.
     */
    public function getStepByStep(float $originLat, float $originLng, float $destLat, float $destLng): array
    {
        $route = $this->getRoute($originLat, $originLng, $destLat, $destLng);

        $steps = [];
        $totalDistance = 0;
        $totalDuration = 0;

        $legs = $route['legs'] ?? $route['paths'][0]['legs'] ?? [];
        foreach ($legs as $leg) {
            foreach ($leg['steps'] ?? [] as $step) {
                $instruction = $step['instruction'] ?? $step['html_instructions'] ?? '';
                $steps[] = [
                    'instruction' => strip_tags($instruction),
                    'distance' => ($step['distance']['value'] ?? 0) / 1000,
                    'duration_sec' => $step['duration']['value'] ?? 0,
                    'direction' => $step['maneuver'] ?? 'straight',
                ];
                $totalDistance += $step['distance']['value'] ?? 0;
                $totalDuration += $step['duration']['value'] ?? 0;
            }
        }

        return [
            'steps' => $steps,
            'total_distance_km' => round($totalDistance / 1000, 2),
            'total_duration_min' => round($totalDuration / 60),
            'navigation_url' => $this->getNavigationUrl($destLat, $destLng),
        ];
    }

    protected function olaMapsRoute(float $originLat, float $originLng, float $destLat, float $destLng): array
    {
        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->olaApiKey,
            ])->get('https://api.olamaps.io/routing/v1/directions', [
                'origin' => "{$originLat},{$originLng}",
                'destination' => "{$destLat},{$destLng}",
                'mode' => 'driving',
                'alternatives' => 'false',
            ]);

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            Log::error("Ola Maps routing failed: {$e->getMessage()}");
        }

        return $this->fallbackRoute($originLat, $originLng, $destLat, $destLng);
    }

    protected function fallbackRoute(float $originLat, float $originLng, float $destLat, float $destLng): array
    {
        $distance = $this->haversine($originLat, $originLng, $destLat, $destLng);
        $durationMin = ceil($distance / 30 * 60);

        return [
            'paths' => [[
                'legs' => [[
                    'steps' => [
                        ['instruction' => 'Head towards the destination', 'distance' => ['value' => $distance * 1000], 'duration' => ['value' => $durationMin * 60]],
                    ],
                    'distance' => ['value' => $distance * 1000],
                    'duration' => ['value' => $durationMin * 60],
                ]],
            ]],
            'total_distance_km' => round($distance, 2),
            'total_duration_min' => $durationMin,
        ];
    }

    protected function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
