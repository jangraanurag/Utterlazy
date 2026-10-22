<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MealPlanDelivery extends Model
{
    use HasUuids;

    protected $table = 'meal_plan_deliveries';

    protected $fillable = [
        'subscription_id', 'scheduled_date', 'meal_time',
        'delivered_at', 'status', 'order_id', 'notes',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'delivered_at' => 'datetime',
    ];

    public function subscription()
    {
        return $this->belongsTo(MealPlanSubscription::class, 'subscription_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
