<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SosAlert extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'sos_alerts';

    protected $fillable = [
        'rider_id',
        'order_id',
        'latitude',
        'longitude',
        'status',
        'incident_id',
        'acknowledged_by',
        'acknowledged_at',
        'resolved_at',
        'resolution_note',
        'metadata',
        'triggered_at',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
        'triggered_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function rider()
    {
        return $this->belongsTo(Rider::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function incident()
    {
        return $this->belongsTo(OpsIncident::class, 'incident_id');
    }
}
