<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Invoice extends Model
{
    use HasUuids;

    protected $connection = 'catalog_mysql';

    protected $fillable = [
        'restaurant_id',
        'type',
        'invoice_number',
        'period_start',
        'period_end',
        'amount',
        'gst_rate',
        'gst_amount',
        'total_amount',
        'commission_rate',
        'status',
        'admin_notes',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }
}
