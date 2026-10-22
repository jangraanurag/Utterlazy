<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CallQueue extends Model
{
    protected $fillable = [
        'order_id',
        'call_type',
        'from_phone',
        'to_phone',
        'payload',
        'status',
        'priority',
        'attempts',
        'max_attempts',
        'error',
        'scheduled_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'scheduled_at' => 'datetime',
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'priority' => 'integer',
    ];

    public function scopePending($query)
    {
        return $query->where('status', 'pending')
            ->where('scheduled_at', '<=', now());
    }

    public function scopeByPriority($query)
    {
        return $query->orderBy('priority')->orderBy('created_at');
    }
}
