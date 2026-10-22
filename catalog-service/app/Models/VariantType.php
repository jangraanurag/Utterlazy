<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class VariantType extends Model
{
    use HasUuids;

    protected $fillable = ['menu_item_id', 'axis', 'unit'];

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function variants()
    {
        return $this->hasMany(Variant::class)->orderBy('sort_order');
    }
}
