<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RestaurantPackagingSetting extends Model
{
    use HasUuids;

    protected $fillable = ['restaurant_id', 'charge_type', 'charge_value', 'tax_type', 'is_active'];

    protected $casts = [
        'charge_value' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }
}
