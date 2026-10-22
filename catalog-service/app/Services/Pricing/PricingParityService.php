<?php

namespace App\Services\Pricing;

/**
 * Pure pricing mirror of the Laravel checkout formula.
 *
 * Intermediates are NOT rounded (same as today's checkout); only persisted
 * money fields are rounded to 2 dp. This is the reference used to generate
 * golden vectors for the Java order-command pricing calculator.
 *
 * NOT included here (and therefore not yet cutover-safe):
 * - coupon/BOGO/campaign selection (DiscountCalculationService)
 * - wallet balance checks
 * - dynamic delivery estimate inputs (passed in by the adapter)
 */
class PricingParityService
{
    public function calculate(array $in): array
    {
        $subtotal = (float) $in['subtotal'];
        $vendorRate = (float) ($in['vendor_discount_rate'] ?? 5.0);
        $taxRate = (float) ($in['tax_rate'] ?? 5.0);
        $deliveryMin = (float) ($in['delivery_min'] ?? 10.0);
        $deliveryMax = (float) ($in['delivery_max'] ?? 50.0);
        $deliveryEstimate = (float) ($in['delivery_estimate'] ?? 27.0);
        $freeAbove = (float) ($in['delivery_free_above'] ?? 500.0);
        $platformFee = (float) ($in['platform_fee'] ?? 5.0);
        $couponInput = (float) ($in['coupon'] ?? 0.0);
        $tip = (float) ($in['tip'] ?? 0.0);
        $commissionRate = (float) ($in['commission_rate'] ?? 15.0);
        $commissionGstRate = (float) ($in['commission_gst_rate'] ?? 18.0);

        $vendorDiscount = $subtotal * ($vendorRate / 100);
        $afterDiscount = $subtotal - $vendorDiscount;
        $tax = $afterDiscount * ($taxRate / 100);

        $deliveryFee = round(max($deliveryMin, min($deliveryEstimate, $deliveryMax)), 2);
        if ($subtotal >= $freeAbove) {
            $deliveryFee = 0.0;
        }

        $grandTotal = $afterDiscount + $tax + $deliveryFee + $platformFee;

        $couponDiscount = min($couponInput, $grandTotal);
        $grandTotal -= $couponDiscount;
        $grandTotal += $tip;

        $netOrderValue = $subtotal - $vendorDiscount - $couponDiscount;
        $commission = max(0, $netOrderValue * ($commissionRate / 100));
        $commissionGst = $commission * ($commissionGstRate / 100);
        $netPayout = ($netOrderValue + $tax) - ($commission + $commissionGst);

        return [
            'subtotal' => round($subtotal, 2),
            'vendor_discount' => round($vendorDiscount, 2),
            'tax' => round($tax, 2),
            'delivery_fee' => round($deliveryFee, 2),
            'platform_fee' => round($platformFee, 2),
            'coupon_discount' => round($couponDiscount, 2),
            'tip' => round($tip, 2),
            'grand_total' => round($grandTotal, 2),
            'net_order_value' => round($netOrderValue, 2),
            'commission' => round($commission, 2),
            'commission_gst' => round($commissionGst, 2),
            'net_payout' => round($netPayout, 2),
        ];
    }
}
