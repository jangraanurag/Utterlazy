<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestaurantApprovalOtp extends Model
{
    protected $table = 'restaurant_approval_otps';

    protected $fillable = ['restaurant_id', 'entity_type', 'entity_id', 'otp_hash', 'expires_at', 'attempt_count', 'verified_at'];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'attempt_count' => 'integer',
    ];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }
}
