<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Membership extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'plan', 'starts_at', 'expires_at', 'is_active', 'benefits'];

    protected $casts = ['starts_at' => 'datetime', 'expires_at' => 'datetime', 'is_active' => 'boolean', 'benefits' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->is_active && $this->expires_at && $this->expires_at->isFuture();
    }
}
