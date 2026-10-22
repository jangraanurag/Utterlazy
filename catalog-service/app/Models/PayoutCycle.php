<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PayoutCycle extends Model
{
    use HasUuids;

    protected $fillable = [
        'restaurant_id', 'cycle_start', 'cycle_end', 'generated_at', 'status',
        'gross_order_value', 'total_deductions', 'total_additions', 'net_payable',
        'previous_balance_carried', 'utr_number', 'payment_date', 'payment_mode',
        'amount_paid', 'payment_notes', 'entered_by', 'approved_by', 'approved_at',
    ];

    protected $casts = [
        'cycle_start' => 'date',
        'cycle_end' => 'date',
        'generated_at' => 'datetime',
        'payment_date' => 'date',
        'approved_at' => 'datetime',
        'gross_order_value' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'total_additions' => 'decimal:2',
        'net_payable' => 'decimal:2',
        'previous_balance_carried' => 'decimal:2',
        'amount_paid' => 'decimal:2',
    ];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function orderLines()
    {
        return $this->hasMany(PayoutOrderLine::class, 'cycle_id');
    }

    public function deductions()
    {
        return $this->hasMany(PayoutDeduction::class, 'cycle_id');
    }

    public function additions()
    {
        return $this->hasMany(PayoutAddition::class, 'cycle_id');
    }

    public function enteredBy()
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
