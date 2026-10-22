<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SurgePricingConfig extends Model
{
    use HasUuids;

    protected $table = 'surge_pricing_configs';

    protected $fillable = [
        'city', 'time_slots', 'weather_multipliers',
        'demand_thresholds', 'is_active',
    ];

    protected $casts = [
        'time_slots' => 'array',
        'weather_multipliers' => 'array',
        'demand_thresholds' => 'array',
        'is_active' => 'boolean',
    ];
}
