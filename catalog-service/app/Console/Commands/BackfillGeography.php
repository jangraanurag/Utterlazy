<?php

namespace App\Console\Commands;

use App\Models\Restaurant;
use App\Models\Rider;
use Illuminate\Console\Command;

class BackfillGeography extends Command
{
    protected $signature = 'geo:backfill';

    protected $description = 'Backfill state/district on restaurants and riders from address text (§7)';

    private const STATES = [
        'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh', 'Goa', 'Gujarat',
        'Haryana', 'Himachal Pradesh', 'Jharkhand', 'Karnataka', 'Kerala', 'Madhya Pradesh',
        'Maharashtra', 'Manipur', 'Meghalaya', 'Mizoram', 'Nagaland', 'Odisha', 'Punjab',
        'Rajasthan', 'Sikkim', 'Tamil Nadu', 'Telangana', 'Tripura', 'Uttar Pradesh',
        'Uttarakhand', 'West Bengal', 'Andaman and Nicobar Islands', 'Chandigarh',
        'Dadra and Nagar Haveli and Daman and Diu', 'Delhi', 'Jammu and Kashmir',
        'Ladakh', 'Lakshadweep', 'Puducherry',
    ];

    public function handle(): int
    {
        $parsed = 0;
        $unmatched = 0;

        foreach (Restaurant::whereNull('state')->orWhereNull('district')->get() as $restaurant) {
            $geo = $this->parseAddress($restaurant->address);
            if ($geo === null) {
                $unmatched++;
                continue;
            }
            $restaurant->update($geo);
            $parsed++;
        }

        foreach (Rider::whereNull('state')->orWhereNull('district')->get() as $rider) {
            $geo = $this->parseAddress(implode(', ', array_filter([
                $rider->city ?? null,
                $rider->state ?? null,
                $rider->address ?? null,
            ])));
            if ($geo === null) {
                $unmatched++;
                continue;
            }
            $rider->update($geo);
            $parsed++;
        }

        $this->info("Backfill done: {$parsed} matched, {$unmatched} unmatched");

        return self::SUCCESS;
    }

    /**
     * Parse "City, State PIN" style addresses into state + district.
     */
    private function parseAddress(?string $address): ?array
    {
        if (! $address) {
            return null;
        }

        $normalized = preg_replace('/\s+/', ' ', trim($address));

        $state = null;
        $city = null;

        foreach (self::STATES as $stateName) {
            if (stripos($normalized, $stateName) !== false) {
                $state = $stateName;
                break;
            }
        }

        if ($state === null) {
            // Last-ditch: match the pincode-adjacent text "Bhiwani, Haryana 127021" — require known pattern
            if (preg_match('/,\s*([A-Za-z ]+?)\s+(\d{6})/', $normalized, $m)) {
                $city = trim($m[1]);
            }
            return $city ? ['district' => $city] : null;
        }

        $before = substr($normalized, 0, stripos($normalized, $state));
        $segments = array_values(array_filter(array_map('trim', explode(',', $before))));
        $city = end($segments) ?: null;

        // City may be glued as "Bhiwani Haryana 127021" without comma — try pincode backtrack
        if (! $city && preg_match('/([A-Za-z ]+?)\s+' . preg_quote($state, '/') . '/', $normalized, $m)) {
            $city = trim($m[1]);
        }

        if (! $city && preg_match('/,\s*([A-Za-z ]+?)\s+' . preg_quote($state, '/') . '/i', $normalized, $m)) {
            $city = trim($m[1]);
        }

        return $city
            ? ['state' => $state, 'district' => $city]
            : ['state' => $state];
    }
}
