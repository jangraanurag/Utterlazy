<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RiderPayoutCycle extends Model
{
    use HasUuids;

    protected $fillable = [
        'rider_id', 'cycle_date', 'status',
        'total_orders', 'total_distance_km',
        'distance_pay', 'base_pay', 'surge_pay', 'wait_pay',
        'tip_amount', 'incentive_bonus', 'penalty',
        'total_adjustments', 'net_payable',
        'utr_number', 'payment_date', 'payment_mode', 'payment_notes',
        'entered_by', 'approved_by', 'approved_at',
    ];

    protected $casts = [
        'cycle_date' => 'date',
        'payment_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function rider()
    {
        return $this->belongsTo(Rider::class);
    }

    public function enteredBy()
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['generated', 'pending_payment']);
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }
}
