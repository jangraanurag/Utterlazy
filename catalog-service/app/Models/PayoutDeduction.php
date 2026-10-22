<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PayoutDeduction extends Model
{
    use HasUuids;

    protected $fillable = [
        'cycle_id', 'type', 'amount', 'reference_type', 'reference_id', 'notes', 'added_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function cycle()
    {
        return $this->belongsTo(PayoutCycle::class, 'cycle_id');
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
