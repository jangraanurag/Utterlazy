<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Trip extends Model
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
        'rider_id', 'stop_count', 'total_distance_km',
        'total_earnings', 'status', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'total_distance_km' => 'float',
        'total_earnings' => 'float',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function rider()
    {
        return $this->belongsTo(Rider::class);
    }

    public function stops()
    {
        return $this->hasMany(TripStop::class);
    }

    public function orders()
    {
        return $this->hasManyThrough(Order::class, TripStop::class, 'trip_id', 'id', 'id', 'order_id');
    }
}
