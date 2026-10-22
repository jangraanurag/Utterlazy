<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'order_id',
        'menu_item_id',
        'quantity',
        'unit_price',
        'is_mrp',
        'total_price',
        'variant_snapshot',
        'addons_snapshot',
        'packaging_charge',
        'packaging_tax',
        'item_tax',
        'addon_subtotal',
        'addon_tax',
    ];

    protected $casts = [
        'variant_snapshot' => 'array',
        'addons_snapshot' => 'array',
        'packaging_charge' => 'decimal:2',
        'packaging_tax' => 'decimal:2',
        'item_tax' => 'decimal:2',
        'addon_subtotal' => 'decimal:2',
        'addon_tax' => 'decimal:2',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }
}
