<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'restaurant_id', 'order_id',
        'rating', 'title', 'comment',
        'food_rating', 'delivery_rating', 'packaging_rating',
        'photos', 'helpful_count', 'is_reported', 'report_reason',
        'vendor_reply', 'vendor_replied_at',
        'refund_amount', 'refund_status', 'refunded_at',
    ];

    protected $casts = [
        'photos' => 'array',
        'helpful_count' => 'integer',
        'is_reported' => 'boolean',
        'vendor_replied_at' => 'datetime',
        'refund_amount' => 'decimal:2',
        'refunded_at' => 'datetime',
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
