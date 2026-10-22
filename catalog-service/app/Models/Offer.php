<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Offer extends Model
{
    use HasUuids;

    protected $fillable = [
        'restaurant_id', 'type', 'title', 'description',
        'discount_value', 'max_discount', 'min_order_value',
        'applicable_type', 'applicable_ids',
        'budget', 'used_count', 'start_date', 'end_date',
        'schedule', 'status',
        'created_by_type', 'created_by_id',
        'approval_status', 'approved_at', 'approved_via', 'rejection_reason',
    ];

    protected $casts = [
        'applicable_ids' => 'array',
        'schedule' => 'array',
        'discount_value' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'min_order_value' => 'decimal:2',
        'budget' => 'decimal:2',
        'used_count' => 'integer',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }
}
