<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AddOnItem extends Model
{
    use HasUuids;

    protected $fillable = ['add_on_group_id', 'name', 'price', 'tax_type', 'is_available', 'sort_order'];

    protected $casts = [
        'price' => 'decimal:2',
        'is_available' => 'boolean',
    ];

    public function addOnGroup()
    {
        return $this->belongsTo(AddOnGroup::class);
    }
}
