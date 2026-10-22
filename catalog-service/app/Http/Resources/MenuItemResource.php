<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'restaurant_id' => $this->restaurant_id,
            'category_id' => $this->category_id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => (float) $this->price,
            'image_url' => $this->cover_image_url,
            'is_available' => $this->is_available,
            'modifiers' => $this->modifiers,

            // New fields
            'prep_type' => $this->prep_type,
            'fasting_suitable' => $this->fasting_suitable,
            'tax_type' => $this->tax_type,
            'tax_included' => $this->tax_included,
            'serves_count' => $this->serves_count,
            'category_name' => $this->category?->name,

            // Variants
            'variant_type' => $this->when($this->relationLoaded('variantType'), fn () => $this->variantType ? [
                'id' => $this->variantType->id,
                'axis' => $this->variantType->axis,
                'unit' => $this->variantType->unit,
                'variants' => $this->variantType->relationLoaded('variants')
                    ? $this->variantType->variants->map(fn ($v) => [
                        'id' => $v->id,
                        'label' => $v->label,
                        'unit' => $v->unit,
                        'price_impact' => (float) $v->price_impact,
                        'is_default' => $v->is_default,
                    ])
                    : [],
            ] : null
            ),

            // Add-ons
            'add_on_groups' => $this->when($this->relationLoaded('addOnGroups'), fn () => $this->addOnGroups->map(fn ($g) => [
                'id' => $g->id,
                'name' => $g->name,
                'selection_type' => $g->selection_type,
                'min_select' => $g->min_select,
                'max_select' => $g->max_select,
                'items' => $g->relationLoaded('items')
                    ? $g->items->where('is_available', true)->values()->map(fn ($i) => [
                        'id' => $i->id,
                        'name' => $i->name,
                        'price' => (float) $i->price,
                        'tax_type' => $i->tax_type,
                    ])
                    : [],
            ])
            ),

            // Images
            'images' => $this->when($this->relationLoaded('images'), fn () => $this->images->map(fn ($img) => [
                'id' => $img->id,
                'image_url' => $img->image_url,
                'is_cover' => $img->is_cover,
                'sort_order' => $img->sort_order,
            ])
            ),

            // Packaging
            'packaging' => [
                'level' => $this->packaging_charge_level,
                'charge_type' => $this->packaging_charge_type,
                'charge_value' => $this->packaging_charge_value ? (float) $this->packaging_charge_value : null,
                'tax_type' => $this->packaging_tax_type,
            ],

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
