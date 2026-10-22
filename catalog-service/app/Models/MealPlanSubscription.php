<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MealPlanSubscription extends Model
{
    use HasUuids;

    protected $table = 'meal_plan_subscriptions';

    protected $fillable = [
        'user_id', 'meal_plan_id', 'restaurant_id', 'address_id',
        'start_date', 'end_date', 'meal_time', 'dietary_notes',
        'total_amount', 'payment_status', 'status', 'cancelled_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'cancelled_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function mealPlan()
    {
        return $this->belongsTo(MealPlan::class);
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function address()
    {
        return $this->belongsTo(Address::class);
    }

    public function deliveries()
    {
        return $this->hasMany(MealPlanDelivery::class, 'subscription_id');
    }

    public function activeDeliveries()
    {
        return $this->deliveries()->whereDate('scheduled_date', '>=', today());
    }
}
