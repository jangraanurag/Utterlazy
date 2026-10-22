<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaHandoff extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'wa_handoffs';

    protected $fillable = [
        'wa_id',
        'order_id',
        'reason',
        'status',
        'assignee_id',
        'assigned_at',
        'closed_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public static function openFor(string $waId): ?self
    {
        return static::where('wa_id', $waId)->where('status', '!=', 'closed')->latest()->first();
    }
}
