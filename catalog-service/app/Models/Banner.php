<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    use HasUuids;

    protected $fillable = ['title', 'description', 'image_url', 'link_type', 'link_value', 'link_url', 'position', 'target_audience', 'is_active', 'sort_order', 'start_date', 'end_date'];

    protected $casts = ['is_active' => 'boolean', 'start_date' => 'datetime', 'end_date' => 'datetime'];
}
