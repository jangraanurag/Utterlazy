<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TripStop extends Model
{
    use HasUuids;

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model): void {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) \Illuminate\Support\Str::orderedUuid();
            }
        });
    }

    protected $fillable = [
        'trip_id', 'order_id', 'stop_order', 'stop_type',
        'status', 'distance_from_prev_km', 'arrived_at', 'completed_at',
    ];

    protected $casts = [
        'distance_from_prev_km' => 'float',
        'arrived_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
