<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RestaurantAnalytics extends Model
{
    use HasUuids;

    protected $fillable = ['restaurant_id', 'date', 'total_orders', 'total_revenue', 'avg_order_value', 'new_customers', 'returning_customers', 'avg_rating', 'total_ratings', 'peak_hours', 'top_items'];

    protected $casts = ['date' => 'date', 'total_revenue' => 'decimal:2', 'avg_order_value' => 'decimal:2', 'avg_rating' => 'decimal:2', 'peak_hours' => 'array', 'top_items' => 'array'];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }
}
