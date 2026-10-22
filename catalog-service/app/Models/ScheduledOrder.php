<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ScheduledOrder extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'restaurant_id', 'items', 'address_id',
        'scheduled_at', 'payment_mode', 'coupon_code', 'notes',
        'status', 'order_id',
    ];

    protected $casts = [
        'items' => 'array',
        'scheduled_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
