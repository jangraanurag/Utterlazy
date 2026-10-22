<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class VendorPayment extends Model
{
    use HasUuids;

    protected $connection = 'catalog_mysql';

    protected $fillable = [
        'restaurant_id',
        'payment_period',
        'week_start',
        'week_end',
        'total_order_value',
        'total_discounts',
        'total_commission',
        'commission_gst',
        'total_sponsorship',
        'sponsorship_gst',
        'net_payable',
        'status',
        'admin_notes',
        'utr_number',
        'approved_by',
        'approved_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'week_start' => 'date',
            'week_end' => 'date',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }
}
