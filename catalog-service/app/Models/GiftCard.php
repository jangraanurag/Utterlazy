<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class GiftCard extends Model
{
    use HasUuids;

    protected $fillable = ['code', 'initial_amount', 'remaining_amount', 'buyer_id', 'recipient_id', 'recipient_email', 'message', 'is_redeemed', 'expires_at'];

    protected $casts = ['initial_amount' => 'decimal:2', 'remaining_amount' => 'decimal:2', 'is_redeemed' => 'boolean', 'expires_at' => 'datetime'];

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }
}
