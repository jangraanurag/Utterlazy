<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Restaurant extends Model
{
    use HasUuids;

    protected $connection = 'catalog_mysql';

    protected $fillable = ['name', 'description', 'logo_url', 'is_active', 'operating_hours'];

    protected $casts = [
        'operating_hours' => 'array',
        'is_active' => 'boolean',
    ];
}
