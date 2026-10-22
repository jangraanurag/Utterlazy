<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class GstLiabilityRegister extends Model
{
    use HasUuids;

    protected $table = 'gst_liability_register';

    protected $fillable = [
        'filing_period', 'state', 'total_gst_collected', 'total_gst_deposited', 'status', 'deposited_at', 'notes',
    ];

    protected $casts = [
        'total_gst_collected' => 'decimal:2',
        'total_gst_deposited' => 'decimal:2',
        'deposited_at' => 'datetime',
    ];
}
