<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class EvChargingStation extends Model
{
    use HasUuids;

    protected $table = 'ev_charging_stations';

    protected $fillable = [
        'name', 'address', 'latitude', 'longitude',
        'available_slots', 'is_active',
    ];
}
