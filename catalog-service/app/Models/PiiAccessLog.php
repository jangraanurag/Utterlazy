<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PiiAccessLog extends Model
{
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'pii_access_logs';

    protected $fillable = [
        'actor_user_id',
        'actor_role',
        'action',
        'resource_type',
        'resource_id',
        'filters',
        'row_count',
        'purpose',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'row_count' => 'integer',
        'created_at' => 'datetime',
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
