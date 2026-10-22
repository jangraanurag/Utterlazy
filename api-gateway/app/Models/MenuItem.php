<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

class MenuItem extends Model
{
    use HasUuids;

    protected $connection = 'catalog_mysql';

    protected $fillable = [
        'restaurant_id', 'category_id', 'name', 'description', 
        'price', 'image_url', 'is_available', 'modifiers'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_available' => 'boolean',
        'modifiers' => 'array',
    ];
}
