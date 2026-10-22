<?php

namespace App\Services\Pricing;

class PricingParityComparator
{
    public const FIELDS = [
        'grandTotal',
        'tax',
        'vendorDiscount',
        'deliveryFee',
        'platformFee',
        'couponDiscount',
        'commission',
        'netPayout',
    ];

    /**
     * Compare canonical price fields. Values are compared as 2dp strings, so
     * "230.5" and "230.50" match while a paise difference does not.
     *
     * @return array<string, array{laravel:?string, java:?string}>
     */
    public function compare(array $expected, array $actual): array
    {
        $diffs = [];

        foreach (self::FIELDS as $field) {
            $hasExpected = array_key_exists($field, $expected);
            $hasActual = array_key_exists($field, $actual);

            if (! $hasExpected || ! $hasActual) {
                $diffs[$field] = [
                    'laravel' => $hasExpected ? $this->money($expected[$field]) : null,
                    'java' => $hasActual ? $this->money($actual[$field]) : null,
                ];

                continue;
            }

            $expectedValue = $this->money($expected[$field]);
            $actualValue = $this->money($actual[$field]);

            if ($expectedValue !== $actualValue) {
                $diffs[$field] = ['laravel' => $expectedValue, 'java' => $actualValue];
            }
        }

        return $diffs;
    }

    private function money($value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
