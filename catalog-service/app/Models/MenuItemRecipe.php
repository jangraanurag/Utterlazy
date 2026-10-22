<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MenuItemRecipe extends Model
{
    use HasUuids;

    protected $table = 'menu_item_recipes';

    protected $fillable = [
        'restaurant_id', 'menu_item_id', 'inventory_item_id', 'quantity_required',
    ];

    protected $casts = [
        'quantity_required' => 'float',
    ];

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
