<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AddOnGroup extends Model
{
    use HasUuids;

    protected $fillable = [
        'restaurant_id', 'name', 'selection_type', 'min_select', 'max_select', 'sort_order',
    ];

    protected $casts = [
        'min_select' => 'integer',
        'max_select' => 'integer',
    ];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function items()
    {
        return $this->hasMany(AddOnItem::class)->orderBy('sort_order');
    }

    public function menuItems()
    {
        return $this->belongsToMany(MenuItem::class, 'menu_item_add_on_group');
    }
}
