<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RestaurantStaff extends Model
{
    use HasUuids;

    protected $fillable = [
        'restaurant_id', 'name', 'email', 'phone',
        'role', 'permissions', 'is_active',
    ];

    protected $casts = [
        'permissions' => 'array',
        'is_active' => 'boolean',
    ];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }
}
