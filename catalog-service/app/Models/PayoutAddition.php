<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PayoutAddition extends Model
{
    use HasUuids;

    protected $fillable = [
        'cycle_id', 'type', 'amount', 'reference_type', 'reference_id', 'notes', 'added_by', 'approved_by', 'approved_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function cycle()
    {
        return $this->belongsTo(PayoutCycle::class, 'cycle_id');
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
