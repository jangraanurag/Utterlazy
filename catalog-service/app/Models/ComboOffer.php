<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ComboOffer extends Model
{
    use HasUuids;

    protected $fillable = ['restaurant_id', 'name', 'description', 'items', 'combo_price', 'original_price', 'is_active'];

    protected $casts = ['items' => 'array', 'combo_price' => 'decimal:2', 'original_price' => 'decimal:2', 'is_active' => 'boolean'];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }
}
