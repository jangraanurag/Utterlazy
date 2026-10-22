<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DailyRiderMetric extends Model
{
    use HasUuids;

    protected $table = 'daily_rider_metrics';

    protected $fillable = [
        'rider_id', 'date',
        'orders_completed', 'orders_cancelled',
        'total_earnings', 'total_distance_km', 'total_online_minutes',
        'restaurant_breakdown', 'time_slot_breakdown', 'hourly_earnings',
    ];

    protected $casts = [
        'date' => 'date',
        'orders_completed' => 'integer',
        'orders_cancelled' => 'integer',
        'total_earnings' => 'decimal:2',
        'total_distance_km' => 'decimal:2',
        'total_online_minutes' => 'integer',
        'restaurant_breakdown' => 'array',
        'time_slot_breakdown' => 'array',
        'hourly_earnings' => 'array',
    ];

    public function rider()
    {
        return $this->belongsTo(Rider::class);
    }
}
