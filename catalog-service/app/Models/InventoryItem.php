<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'restaurant_id', 'name', 'unit', 'stock_quantity',
        'low_stock_threshold', 'cost_per_unit', 'is_active',
    ];

    protected $casts = [
        'stock_quantity' => 'float',
        'low_stock_threshold' => 'float',
        'cost_per_unit' => 'float',
        'is_active' => 'boolean',
    ];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function recipes()
    {
        return $this->hasMany(MenuItemRecipe::class);
    }

    public function isLowStock(): bool
    {
        return $this->low_stock_threshold > 0 && $this->stock_quantity <= $this->low_stock_threshold;
    }
}
