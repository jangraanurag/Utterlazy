<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'state', 'is_active', 'base_delivery_fee', 'base_commission_rate'];

    protected $casts = ['is_active' => 'boolean', 'base_delivery_fee' => 'decimal:2', 'base_commission_rate' => 'decimal:2'];

    public function deliveryZones()
    {
        return $this->hasMany(DeliveryZone::class);
    }
}
