<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BogoDiscount extends Model
{
    protected $fillable = [
        'restaurant_id', 'title', 'buy_quantity', 'get_quantity',
        'user_type', 'time_from', 'time_to', 'all_day',
        'start_date', 'end_date', 'is_active',
    ];

    protected $casts = [
        'buy_quantity' => 'integer',
        'get_quantity' => 'integer',
        'all_day' => 'boolean',
        'is_active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
        'time_from' => 'datetime:H:i',
        'time_to' => 'datetime:H:i',
    ];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function menuItems()
    {
        return $this->belongsToMany(MenuItem::class, 'bogo_menu_items', 'bogo_discount_id', 'menu_item_id');
    }

    public function isValidFor(float $subtotal, ?int $pastOrders, float $currentHour): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if ($subtotal < 0) {
            return false;
        }
        if (now()->lt($this->start_date)) {
            return false;
        }
        if ($this->end_date && now()->gt($this->end_date)) {
            return false;
        }
        if (! $this->all_day && $this->time_from && $this->time_to) {
            $from = strtotime($this->time_from) / 3600;
            $to = strtotime($this->time_to) / 3600;
            $withinWindow = $currentHour >= $from && $currentHour <= $to;
            if ($to < $from) {
                $withinWindow = $currentHour >= $from || $currentHour <= $to;
            } // overnight
            if (! $withinWindow) {
                return false;
            }
        }
        if ($this->user_type === 'new_users' && ($pastOrders === null || $pastOrders > 0)) {
            return false;
        }

        return true;
    }
}
