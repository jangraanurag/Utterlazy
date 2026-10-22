<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    use HasUuids;

    protected $fillable = ['cart_id', 'menu_item_id', 'quantity', 'variant_id', 'add_on_item_ids', 'special_instructions'];

    protected $casts = [
        'add_on_item_ids' => 'array',
    ];

    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function variant()
    {
        return $this->belongsTo(Variant::class);
    }
}
