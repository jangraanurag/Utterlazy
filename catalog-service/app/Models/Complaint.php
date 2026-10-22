<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Complaint extends Model
{
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'order_id',
        'restaurant_id',
        'reason',
        'description',
        'status',
        'vendor_response',
        'refund_amount',
        'refund_processed',
    ];

    protected $casts = [
        'refund_amount' => 'decimal:2',
        'refund_processed' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
