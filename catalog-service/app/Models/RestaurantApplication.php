<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RestaurantApplication extends Model
{
    use HasUuids;

    protected $fillable = [
        'restaurant_name', 'owner_name', 'email', 'phone',
        'fssai_number', 'gst_number', 'pan_number',
        'address', 'city', 'state', 'pincode',
        'latitude', 'longitude', 'cuisine_type',
        'status', 'rejection_reason', 'documents',
        'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'documents' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
