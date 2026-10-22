<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FestivalCampaign extends Model
{
    use HasUuids;

    protected $table = 'festival_campaigns';

    protected $fillable = [
        'name', 'slug', 'start_date', 'end_date', 'description',
        'regions', 'config', 'is_active',
    ];

    protected $casts = [
        'regions' => 'array',
        'config' => 'array',
        'is_active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
    ];
}
