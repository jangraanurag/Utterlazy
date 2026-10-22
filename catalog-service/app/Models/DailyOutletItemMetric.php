<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DailyOutletItemMetric extends Model
{
    use HasUuids;

    protected $fillable = [
        'restaurant_id', 'menu_item_id', 'date', 'orders', 'revenue',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'revenue' => 'decimal:2',
        ];
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }
}
