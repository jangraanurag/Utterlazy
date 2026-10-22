<?php

namespace App\Services;

use App\Models\BogoDiscount;
use App\Models\Coupon;
use App\Models\OptInCampaign;
use Illuminate\Support\Collection;

class DiscountCalculationService
{
    /**
     * Evaluate all applicable discounts for an order and return the best one.
     * Only one discount applies per order (best value wins).
     *
     * @param  Collection  $activeBogos  Pre-loaded BogoDiscount collection with menuItems relation
     * @param  Collection  $activeCampaigns  Pre-loaded OptInCampaign collection
     * @param  Collection  $cartItems  [{menu_item_id, quantity, unit_price}]
     * @param  Coupon|null  $coupon  Pre-validated coupon model (null if none entered)
     */
    public function calculateBestDiscount(
        float $subtotal,
        Collection $activeBogos,
        Collection $activeCampaigns,
        Collection $cartItems,
        ?Coupon $coupon = null,
        string $restaurantId = '',
        int|string $userId = 0,
    ): array {
        $results = [];

        // 1. Check BOGO
        $bogoResult = $this->evaluateBogo($cartItems, $activeBogos);
        if ($bogoResult) {
            $results[] = $bogoResult;
        }

        // 2. Check opt-in campaigns
        $campaignResult = $this->evaluateOptInCampaign($subtotal, $activeCampaigns, $restaurantId);
        if ($campaignResult) {
            $results[] = $campaignResult;
        }

        // 3. Check coupon
        if ($coupon) {
            $discount = $this->evaluateCoupon($coupon, $subtotal);
            if ($discount !== null) {
                $results[] = $discount;
            }
        }

        if (empty($results)) {
            return [
                'discount_type' => null,
                'discount_amount' => 0.0,
                'label' => null,
                'bogo_items' => [],
                'coupon_id' => null,
            ];
        }

        usort($results, fn ($a, $b) => $b['discount_amount'] <=> $a['discount_amount']);

        return $results[0];
    }

    private function evaluateBogo(Collection $cartItems, Collection $activeBogos): ?array
    {
        $currentHour = (float) now()->format('H.i');

        foreach ($activeBogos as $bogo) {
            if (! $bogo->isValidFor(0, null, $currentHour)) {
                continue;
            }

            $eligibleItemIds = $bogo->menuItems->pluck('id')->toArray();
            $freeItems = [];
            $totalDiscount = 0.0;

            foreach ($cartItems as $item) {
                $menuItemId = $item['menu_item_id'];
                $quantity = (int) $item['quantity'];
                $unitPrice = (float) $item['unit_price'];

                if (! in_array($menuItemId, $eligibleItemIds)) {
                    continue;
                }

                // Same-item BOGO
                $sets = intdiv($quantity, $bogo->buy_quantity + $bogo->get_quantity);
                if ($sets < 1) {
                    if ($quantity < $bogo->buy_quantity) {
                        continue;
                    }
                    $freeCount = min(
                        intdiv($quantity, $bogo->buy_quantity) * $bogo->get_quantity,
                        $quantity - $bogo->buy_quantity
                    );
                } else {
                    $freeCount = $sets * $bogo->get_quantity;
                }

                if ($freeCount > 0) {
                    $discount = $unitPrice * $freeCount;
                    $totalDiscount += $discount;
                    $freeItems[] = [
                        'menu_item_id' => $menuItemId,
                        'free_quantity' => $freeCount,
                        'unit_price' => $unitPrice,
                        'discount' => $discount,
                    ];
                }
            }

            if ($totalDiscount > 0) {
                return [
                    'discount_type' => 'bogo',
                    'discount_amount' => round($totalDiscount, 2),
                    'label' => $bogo->title,
                    'bogo_items' => $freeItems,
                    'bogo_id' => $bogo->id,
                    'coupon_id' => null,
                ];
            }
        }

        return null;
    }

    private function evaluateOptInCampaign(float $subtotal, Collection $activeCampaigns, string $restaurantId): ?array
    {
        $best = null;
        $bestDiscount = 0;

        foreach ($activeCampaigns as $campaign) {
            if ($subtotal < $campaign->min_order_value) {
                continue;
            }
            if (! $campaign->isRestaurantOptedIn($restaurantId)) {
                continue;
            }

            $discount = $campaign->calculateDiscount($subtotal);
            if ($discount > $bestDiscount) {
                $bestDiscount = $discount;
                $best = [
                    'discount_type' => 'campaign',
                    'discount_amount' => $discount,
                    'label' => $campaign->name,
                    'campaign_id' => $campaign->id,
                    'share_platform' => (float) $campaign->share_platform,
                    'share_restaurant' => (float) $campaign->share_restaurant,
                    'bogo_items' => [],
                    'coupon_id' => null,
                ];
            }
        }

        return $best;
    }

    private function evaluateCoupon(?Coupon $coupon, float $subtotal): ?array
    {
        if (! $coupon || ! $coupon->is_active) {
            return null;
        }
        if ($coupon->valid_from && now()->lt($coupon->valid_from)) {
            return null;
        }
        if ($coupon->valid_until && now()->gt($coupon->valid_until)) {
            return null;
        }
        if ($coupon->min_order && $subtotal < $coupon->min_order) {
            return null;
        }
        if ($coupon->usage_limit && $coupon->times_used >= $coupon->usage_limit) {
            return null;
        }

        $discount = $coupon->calculateDiscount($subtotal);

        return [
            'discount_type' => 'coupon',
            'discount_amount' => $discount,
            'label' => $coupon->title ?? $coupon->code,
            'bogo_items' => [],
            'coupon_id' => $coupon->id,
        ];
    }

    public function applyDiscount(float $grandTotal, float $discountAmount): float
    {
        return max(0, round($grandTotal - $discountAmount, 2));
    }
}
