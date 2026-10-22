<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DailyOutletMetric extends Model
{
    use HasUuids;

    protected $fillable = [
        'restaurant_id', 'date', 'overview_category', 'metric_name', 'value',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'value' => 'decimal:2',
        ];
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }
}
