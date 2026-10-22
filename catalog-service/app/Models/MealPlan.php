<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MealPlan extends Model
{
    use HasUuids;

    protected $fillable = [
        'restaurant_id', 'name', 'description', 'price_per_day', 'price_per_week', 'price_per_month',
        'meals_per_day', 'items', 'dietary_tags', 'cuisine_type', 'is_veg', 'is_active',
    ];

    protected $casts = [
        'items' => 'array',
        'dietary_tags' => 'array',
        'is_veg' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(MealPlanSubscription::class);
    }
}
