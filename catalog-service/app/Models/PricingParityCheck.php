<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PricingParityCheck extends Model
{
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'pricing_parity_checks';

    protected $fillable = [
        'order_id',
        'city_id',
        'restaurant_id',
        'matched',
        'diffs',
        'laravel',
        'java',
        'created_at',
    ];

    protected $casts = [
        'matched' => 'boolean',
        'diffs' => 'array',
        'laravel' => 'array',
        'java' => 'array',
        'created_at' => 'datetime',
    ];
}
