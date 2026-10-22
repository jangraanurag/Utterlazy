<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CashReconciliation extends Model
{
    use HasUuids;

    protected $table = 'cash_reconciliations';

    protected $fillable = [
        'rider_id', 'date', 'expected_amount', 'collected_amount',
        'deposited_amount', 'difference', 'status', 'notes',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function rider()
    {
        return $this->belongsTo(Rider::class);
    }
}
