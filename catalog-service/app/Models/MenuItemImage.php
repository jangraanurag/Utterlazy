<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MenuItemImage extends Model
{
    use HasUuids;

    protected $fillable = ['menu_item_id', 'image_url', 'is_cover', 'sort_order'];

    protected $casts = [
        'is_cover' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }
}
