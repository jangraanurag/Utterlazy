<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RiderSosAlert extends Model
{
    use HasUuids;

    protected $table = 'rider_sos_alerts';

    protected $fillable = [
        'rider_id', 'type', 'message', 'latitude', 'longitude', 'status', 'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function rider()
    {
        return $this->belongsTo(Rider::class);
    }
}
