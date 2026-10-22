<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FraudLog extends Model
{
    use HasUuids;

    protected $fillable = ['type', 'entity_type', 'entity_id', 'description', 'evidence', 'severity', 'status'];

    protected $casts = ['evidence' => 'array'];
}
