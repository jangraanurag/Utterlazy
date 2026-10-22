<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class GlobalCategory extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'description', 'image_url', 'is_active', 'sort_order'];

    public function menuItems()
    {
        return $this->hasMany(MenuItem::class, 'global_category_id');
    }
}
