<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasUuids;

    protected $fillable = [
        'restaurant_id',
        'type',
        'invoice_number',
        'period_start',
        'period_end',
        'amount',
        'cgst_rate',
        'cgst_amount',
        'sgst_rate',
        'sgst_amount',
        'convenience_fee',
        'gst_rate',
        'gst_amount',
        'total_amount',
        'commission_rate',
        'status',
        'admin_notes',
        'approved_by',
        'approved_at',
        'pdf_path',
        'excel_path',
        'entered_by',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'approved_at' => 'datetime',
            'amount' => 'decimal:2',
            'cgst_amount' => 'decimal:2',
            'sgst_amount' => 'decimal:2',
            'gst_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'convenience_fee' => 'decimal:2',
        ];
    }

    public function recovery()
    {
        return $this->morphOne(Recovery::class, 'invoiceable');
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
