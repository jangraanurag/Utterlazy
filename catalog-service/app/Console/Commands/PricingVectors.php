<?php

namespace App\Console\Commands;

use App\Services\Pricing\PricingParityService;
use Illuminate\Console\Command;

class PricingVectors extends Command
{
    protected $signature = 'pricing:vectors {--write= : Write the TSV to this path instead of stdout}';

    protected $description = 'Emit canonical pricing vectors (TSV) used by the Java order-command parity check';

    private const CASES = [
        ['id' => 'basic', 'subtotal' => 199.00],
        ['id' => 'free_delivery', 'subtotal' => 600.00],
        ['id' => 'coupon', 'subtotal' => 199.00, 'coupon' => 50.00],
        ['id' => 'tip', 'subtotal' => 199.00, 'tip' => 20.00],
        ['id' => 'no_gst', 'subtotal' => 199.00, 'tax_rate' => 0.0],
        ['id' => 'delivery_min', 'subtotal' => 199.00, 'delivery_estimate' => 5.00],
        ['id' => 'delivery_max', 'subtotal' => 199.00, 'delivery_estimate' => 99.00],
        ['id' => 'combined', 'subtotal' => 450.00, 'coupon' => 30.00, 'tip' => 15.00],
        ['id' => 'paise_edge', 'subtotal' => 333.33, 'coupon' => 11.11, 'tip' => 3.33],
        ['id' => 'free_and_coupon', 'subtotal' => 750.00, 'coupon' => 100.00, 'tip' => 25.00],
    ];

    public function handle(PricingParityService $pricing): int
    {
        $columns = [
            'id', 'subtotal', 'vendor_discount_rate', 'tax_rate',
            'delivery_min', 'delivery_max', 'delivery_estimate', 'delivery_free_above',
            'platform_fee', 'coupon', 'tip', 'commission_rate', 'commission_gst_rate',
            'grand_total', 'vendor_discount', 'tax', 'delivery_fee',
            'coupon_discount', 'commission', 'commission_gst', 'net_payout',
        ];

        $lines = [implode("\t", $columns)];

        foreach (self::CASES as $case) {
            $input = array_merge([
                'vendor_discount_rate' => 5.0,
                'tax_rate' => 5.0,
                'delivery_min' => 10.0,
                'delivery_max' => 50.0,
                'delivery_estimate' => 27.0,
                'delivery_free_above' => 500.0,
                'platform_fee' => 5.0,
                'coupon' => 0.0,
                'tip' => 0.0,
                'commission_rate' => 15.0,
                'commission_gst_rate' => 18.0,
            ], $case);

            $out = $pricing->calculate($input);

            $lines[] = implode("\t", [
                $input['id'],
                $this->money($input['subtotal']),
                $this->money($input['vendor_discount_rate']),
                $this->money($input['tax_rate']),
                $this->money($input['delivery_min']),
                $this->money($input['delivery_max']),
                $this->money($input['delivery_estimate']),
                $this->money($input['delivery_free_above']),
                $this->money($input['platform_fee']),
                $this->money($input['coupon']),
                $this->money($input['tip']),
                $this->money($input['commission_rate']),
                $this->money($input['commission_gst_rate']),
                $this->money($out['grand_total']),
                $this->money($out['vendor_discount']),
                $this->money($out['tax']),
                $this->money($out['delivery_fee']),
                $this->money($out['coupon_discount']),
                $this->money($out['commission']),
                $this->money($out['commission_gst']),
                $this->money($out['net_payout']),
            ]);
        }

        $tsv = implode("\n", $lines)."\n";
        $path = $this->option('write');

        if ($path) {
            file_put_contents($path, $tsv);
            $this->info("Wrote ".count(self::CASES)." pricing vectors to {$path}");

            return self::SUCCESS;
        }

        $this->output->write($tsv);

        return self::SUCCESS;
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
