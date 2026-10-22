<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PayoutOrderLine extends Model
{
    use HasUuids;

    protected $fillable = [
        'cycle_id', 'order_id', 'order_value', 'discount', 'tax', 'commission',
        'convenience_fee', 'delivery_charge_collected', 'net_settlement', 'order_status',
    ];

    protected $casts = [
        'order_value' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'commission' => 'decimal:2',
        'convenience_fee' => 'decimal:2',
        'delivery_charge_collected' => 'decimal:2',
        'net_settlement' => 'decimal:2',
    ];

    public function cycle()
    {
        return $this->belongsTo(PayoutCycle::class, 'cycle_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
