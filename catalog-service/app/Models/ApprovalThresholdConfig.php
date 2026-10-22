<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApprovalThresholdConfig extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'department',
        'unit_type',
        'entity_type',
        'max_amount',
        'sla_hours',
        'geography_scope',
        'is_active',
    ];

    protected $casts = [
        'max_amount' => 'float',
        'geography_scope' => 'array',
        'is_active' => 'boolean',
    ];
}
