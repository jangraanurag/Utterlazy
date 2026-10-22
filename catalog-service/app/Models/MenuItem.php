<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenuItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'restaurant_id', 'category_id', 'global_category_id', 'name', 'name_local', 'description',
        'price', 'is_mrp', 'image_url', 'reel_url', 'is_available', 'modifiers',
        'prep_type', 'fasting_suitable', 'tax_type', 'tax_included', 'serves_count',
        'packaging_charge_level', 'packaging_charge_type', 'packaging_charge_value', 'packaging_tax_type',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_available' => 'boolean',
        'is_mrp' => 'boolean',
        'modifiers' => 'array',
        'fasting_suitable' => 'boolean',
        'tax_included' => 'boolean',
        'packaging_charge_value' => 'decimal:2',
        'serves_count' => 'integer',
    ];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function globalCategory()
    {
        return $this->belongsTo(GlobalCategory::class, 'global_category_id');
    }

    public function variantType()
    {
        return $this->hasOne(VariantType::class);
    }

    public function variants()
    {
        return $this->hasManyThrough(Variant::class, VariantType::class);
    }

    public function defaultVariant()
    {
        return $this->hasOneThrough(Variant::class, VariantType::class)
            ->where('variants.is_default', true);
    }

    public function addOnGroups()
    {
        return $this->belongsToMany(AddOnGroup::class, 'menu_item_add_on_group')
            ->withPivot('sort_order')
            ->orderBy('pivot_sort_order');
    }

    public function images()
    {
        return $this->hasMany(MenuItemImage::class)->orderBy('sort_order');
    }

    public function coverImage()
    {
        return $this->hasOne(MenuItemImage::class)->where('is_cover', true);
    }

    public function getCoverImageUrlAttribute()
    {
        return $this->coverImage?->image_url ?? $this->image_url;
    }

    public function recipes()
    {
        return $this->hasMany(MenuItemRecipe::class);
    }
}
