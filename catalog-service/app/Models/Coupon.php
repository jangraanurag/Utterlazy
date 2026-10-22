<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasUuids;

    protected $fillable = [
        'code', 'title', 'description', 'type', 'value',
        'min_order', 'max_discount', 'usage_limit', 'per_user_limit',
        'times_used', 'is_active', 'valid_from', 'valid_until',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
    ];

    public function usages()
    {
        return $this->hasMany(CouponUsage::class);
    }

    /**
     * Check if coupon is valid for a given user and order amount.
     */
    public function isValidFor(int|string $userId, float $orderAmount): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if ($this->valid_from && now()->lt($this->valid_from)) {
            return false;
        }
        if ($this->valid_until && now()->gt($this->valid_until)) {
            return false;
        }
        if ($this->usage_limit && $this->times_used >= $this->usage_limit) {
            return false;
        }
        if ($orderAmount < $this->min_order) {
            return false;
        }

        // Per-user limit check
        $userUsageCount = $this->usages()->where('user_id', $userId)->count();
        if ($userUsageCount >= $this->per_user_limit) {
            return false;
        }

        return true;
    }

    /**
     * Calculate the discount amount for a given subtotal.
     */
    public function calculateDiscount(float $subtotal): float
    {
        if ($this->type === 'flat') {
            return min($this->value, $subtotal);
        }

        $discount = $subtotal * ($this->value / 100);
        if ($this->max_discount) {
            $discount = min($discount, $this->max_discount);
        }

        return round($discount, 2);
    }
}
