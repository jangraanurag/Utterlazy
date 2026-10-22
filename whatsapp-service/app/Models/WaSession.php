<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaSession extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $primaryKey = 'wa_id';

    protected $fillable = ['wa_id', 'state', 'context', 'expires_at'];

    protected $casts = [
        'context' => 'array',
        'expires_at' => 'datetime',
    ];
}
