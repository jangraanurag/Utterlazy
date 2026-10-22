<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Referral extends Model
{
    use HasUuids;

    protected $fillable = ['referrer_id', 'referred_id', 'code', 'status', 'referrer_reward', 'referred_reward', 'completed_at'];

    protected $casts = ['referrer_reward' => 'decimal:2', 'referred_reward' => 'decimal:2', 'completed_at' => 'datetime'];

    public function referrer()
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referred()
    {
        return $this->belongsTo(User::class, 'referred_id');
    }
}
