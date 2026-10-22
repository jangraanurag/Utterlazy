<?php

namespace App\Services;

use App\Models\AddOnItem;
use App\Models\MenuItem;
use App\Models\RestaurantPackagingSetting;
use App\Models\Variant;
use Illuminate\Support\Collection;

class MenuPricingService
{
    const TAX_RATES = [
        'GST_5' => 0.05,
        'GST_18' => 0.18,
        'NONE' => 0.0,
    ];

    /**
     * Calculate the price of a single menu item with an optional variant.
     *
     * @return array{base_price: float, variant_impact: float, item_price: float, tax_rate: float, tax_amount: float, tax_included: bool, final_price: float}
     */
    public function calculateItemPrice(MenuItem $item, ?Variant $variant = null): array
    {
        $basePrice = (float) $item->price;
        $variantImpact = $variant ? (float) $variant->price_impact : 0;
        $itemPrice = $basePrice + $variantImpact;
        $taxRate = self::TAX_RATES[$item->tax_type] ?? 0.0;
        $taxIncluded = (bool) $item->tax_included;

        if ($taxIncluded) {
            // Price includes tax — back-calculate the tax component for reporting
            $taxAmount = $itemPrice * $taxRate / (1 + $taxRate);
            $finalPrice = $itemPrice;
        } else {
            // Tax is added on top
            $taxAmount = $itemPrice * $taxRate;
            $finalPrice = $itemPrice + $taxAmount;
        }

        return [
            'base_price' => round($basePrice, 2),
            'variant_impact' => round($variantImpact, 2),
            'item_price' => round($itemPrice, 2),
            'tax_rate' => $taxRate,
            'tax_amount' => round($taxAmount, 2),
            'tax_included' => $taxIncluded,
            'final_price' => round($finalPrice, 2),
        ];
    }

    /**
     * Calculate the total for selected add-on items.
     * Add-ons are always tax-excluded (tax added on top).
     *
     * @param  Collection<int, AddOnItem>  $addOnItems
     * @return array{items: array, subtotal: float, total_tax: float, grand_total: float}
     */
    public function calculateAddOnTotal(Collection $addOnItems): array
    {
        $items = [];
        $subtotal = 0.0;
        $totalTax = 0.0;

        foreach ($addOnItems as $addOn) {
            $price = (float) $addOn->price;
            $taxRate = self::TAX_RATES[$addOn->tax_type] ?? 0.0;
            $taxAmount = $price * $taxRate;

            $items[] = [
                'id' => $addOn->id,
                'name' => $addOn->name,
                'price' => round($price, 2),
                'tax_type' => $addOn->tax_type,
                'tax_amount' => round($taxAmount, 2),
                'total' => round($price + $taxAmount, 2),
            ];

            $subtotal += $price;
            $totalTax += $taxAmount;
        }

        return [
            'items' => $items,
            'subtotal' => round($subtotal, 2),
            'total_tax' => round($totalTax, 2),
            'grand_total' => round($subtotal + $totalTax, 2),
        ];
    }

    /**
     * Resolve the packaging charge for an item.
     * Item-level packaging overrides order-level packaging for that item.
     *
     * @param  RestaurantPackagingSetting|null  $orderPackaging  Pre-resolved order-level setting (null = query inside)
     * @param  float  $orderSubtotal  The subtotal of the entire order (used for percentage calculation)
     * @return array{level: string|null, charge_type: string|null, charge_value: float, computed_charge: float, tax_type: string, tax_amount: float, total: float}
     */
    public function resolvePackagingCharge(MenuItem $item, ?RestaurantPackagingSetting $orderPackaging = null, float $orderSubtotal = 0.0): array
    {
        // Item-level overrides
        if ($item->packaging_charge_level === 'item_level' && $item->packaging_charge_value !== null) {
            $chargeValue = (float) $item->packaging_charge_value;
            $computedCharge = $chargeValue;
            $taxType = $item->packaging_tax_type ?? 'NONE';
            $resultLevel = 'item_level';
            $resultChargeType = 'fixed';
        } else {
            // Resolve order-level setting
            if ($orderPackaging === null) {
                $orderPackaging = $item->restaurant->packagingSetting ?? null;
            }

            if ($orderPackaging && $orderPackaging->is_active) {
                $chargeValue = (float) $orderPackaging->charge_value;
                if ($orderPackaging->charge_type === 'percentage') {
                    $computedCharge = $orderSubtotal * ($chargeValue / 100);
                } else {
                    $computedCharge = $chargeValue;
                }
                $taxType = $orderPackaging->tax_type ?? 'NONE';
                $resultLevel = 'order_level';
                $resultChargeType = $orderPackaging->charge_type;
            } else {
                // No packaging configured
                return [
                    'level' => null,
                    'charge_type' => null,
                    'charge_value' => 0.0,
                    'computed_charge' => 0.0,
                    'tax_type' => 'NONE',
                    'tax_amount' => 0.0,
                    'total' => 0.0,
                ];
            }
        }

        $taxRate = self::TAX_RATES[$taxType] ?? 0.0;
        $taxAmount = $computedCharge * $taxRate;

        return [
            'level' => $resultLevel,
            'charge_type' => $resultChargeType,
            'charge_value' => round($chargeValue, 2),
            'computed_charge' => round($computedCharge, 2),
            'tax_type' => $taxType,
            'tax_amount' => round($taxAmount, 2),
            'total' => round($computedCharge + $taxAmount, 2),
        ];
    }

    /**
     * Calculate complete pricing breakdown for a single order line item
     * (one menu item × quantity × variant × add-ons × packaging).
     *
     * @return array{array{pricing: array, addon_total: array, packaging: array, line_total: float}}
     */
    public function calculateLineItem(
        MenuItem $item,
        int $quantity,
        ?Variant $variant = null,
        Collection $addOnItems = new Collection,
        ?RestaurantPackagingSetting $orderPackaging = null,
        float $orderSubtotal = 0.0,
    ): array {
        $pricing = $this->calculateItemPrice($item, $variant);
        $addonTotal = $this->calculateAddOnTotal($addOnItems);
        $packaging = $this->resolvePackagingCharge($item, $orderPackaging, $orderSubtotal);

        // Apply quantity to item pricing
        $itemSubtotal = $pricing['item_price'] * $quantity;
        $itemTaxTotal = $pricing['tax_amount'] * $quantity;
        $itemFinalTotal = $pricing['final_price'] * $quantity;

        // Add-ons are not multiplied by quantity (they're selected once per item)
        $addonSubtotal = $addonTotal['subtotal'];
        $addonTaxTotal = $addonTotal['total_tax'];
        $addonGrandTotal = $addonTotal['grand_total'];

        // Packaging applies once per item line (not per quantity)
        $packagingTotal = $packaging['total'];

        $lineTotal = $itemFinalTotal + $addonGrandTotal + $packagingTotal;

        return [
            'pricing' => $pricing,
            'quantity' => $quantity,
            'item_subtotal' => round($itemSubtotal, 2),
            'item_tax_total' => round($itemTaxTotal, 2),
            'item_final_total' => round($itemFinalTotal, 2),
            'addon_total' => $addonTotal,
            'addon_subtotal' => round($addonSubtotal, 2),
            'addon_tax_total' => round($addonTaxTotal, 2),
            'addon_grand_total' => round($addonGrandTotal, 2),
            'packaging' => $packaging,
            'packaging_total' => round($packagingTotal, 2),
            'line_total' => round($lineTotal, 2),
        ];
    }

    /**
     * Calculate the full order breakdown from a set of line items.
     *
     * @param  array  $lineItems  Array of results from calculateLineItem()
     * @return array{subtotal: float, total_tax: float, total_packaging: float, grand_total: float}
     */
    public function calculateOrderTotal(array $lineItems): array
    {
        $subtotal = 0.0;
        $totalTax = 0.0;
        $totalPackaging = 0.0;

        foreach ($lineItems as $line) {
            $subtotal += $line['item_subtotal'] + $line['addon_subtotal'];
            $totalTax += $line['item_tax_total'] + $line['addon_tax_total'] + $line['packaging']['tax_amount'];
            $totalPackaging += $line['packaging']['computed_charge'];
        }

        $grandTotal = $subtotal + $totalTax + $totalPackaging;

        return [
            'subtotal' => round($subtotal, 2),
            'total_tax' => round($totalTax, 2),
            'total_packaging' => round($totalPackaging, 2),
            'grand_total' => round($grandTotal, 2),
        ];
    }
}
