<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'label', 'flat_building', 'street', 'landmark',
        'city', 'state', 'pincode', 'latitude', 'longitude',
        'location_name', 'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the full formatted address string.
     */
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->flat_building,
            $this->street,
            $this->landmark ? "Near {$this->landmark}" : null,
            $this->city,
            $this->state,
            $this->pincode,
        ]);

        return implode(', ', $parts);
    }
}
