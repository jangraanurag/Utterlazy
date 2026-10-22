<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OptInCampaign extends Model
{
    protected $fillable = [
        'name', 'description', 'discount_type', 'discount_value',
        'max_discount', 'min_order_value',
        'share_platform', 'share_restaurant',
        'start_date', 'end_date', 'is_active',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'min_order_value' => 'decimal:2',
        'share_platform' => 'decimal:2',
        'share_restaurant' => 'decimal:2',
        'is_active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function restaurantOptIns()
    {
        return $this->hasMany(RestaurantCampaignOptIn::class, 'campaign_id');
    }

    public function optedInRestaurants()
    {
        return $this->belongsToMany(Restaurant::class, 'restaurant_campaign_opt_ins', 'campaign_id', 'restaurant_id')
            ->wherePivot('opted_in', true);
    }

    public function isRestaurantOptedIn(string $restaurantId): bool
    {
        return $this->restaurantOptIns()
            ->where('restaurant_id', $restaurantId)
            ->where('opted_in', true)
            ->exists();
    }

    public function calculateDiscount(float $subtotal): float
    {
        if ($this->discount_type === 'flat') {
            return min($this->discount_value, $subtotal);
        }
        $discount = $subtotal * ($this->discount_value / 100);
        if ($this->max_discount) {
            $discount = min($discount, (float) $this->max_discount);
        }

        return round($discount, 2);
    }
}
