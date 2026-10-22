<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RestaurantPayoutBalance extends Model
{
    use HasUuids;

    protected $fillable = [
        'restaurant_id', 'running_balance', 'total_paid_to_date', 'total_earnings_to_date', 'last_updated_at',
    ];

    protected $casts = [
        'running_balance' => 'decimal:2',
        'total_paid_to_date' => 'decimal:2',
        'total_earnings_to_date' => 'decimal:2',
        'last_updated_at' => 'datetime',
    ];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }
}
