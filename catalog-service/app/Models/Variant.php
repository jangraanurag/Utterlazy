<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Variant extends Model
{
    use HasUuids;

    protected $fillable = ['variant_type_id', 'label', 'unit', 'price_impact', 'is_default', 'sort_order'];

    protected $casts = [
        'price_impact' => 'decimal:2',
        'is_default' => 'boolean',
    ];

    public function variantType()
    {
        return $this->belongsTo(VariantType::class);
    }
}
