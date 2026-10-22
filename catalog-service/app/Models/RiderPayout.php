<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiderPayout extends Model
{
    protected $fillable = [
        'rider_id', 'order_id',
        'base_pay', 'distance_pay', 'surge_pay', 'wait_pay',
        'tip_amount', 'incentive_bonus', 'penalty',
        'cash_collected', 'total_payout', 'surge_multiplier',
        'is_settled', 'settled_at',
    ];

    protected $casts = [
        'is_settled' => 'boolean',
        'settled_at' => 'datetime',
    ];

    public function rider()
    {
        return $this->belongsTo(Rider::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
