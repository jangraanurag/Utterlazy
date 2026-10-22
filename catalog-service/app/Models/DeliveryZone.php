<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DeliveryZone extends Model
{
    use HasUuids;

    protected $fillable = ['city_id', 'name', 'boundary_coordinates', 'delivery_fee_multiplier', 'is_active'];

    protected $casts = ['boundary_coordinates' => 'array', 'delivery_fee_multiplier' => 'decimal:2', 'is_active' => 'boolean'];

    public function city()
    {
        return $this->belongsTo(City::class);
    }
}
