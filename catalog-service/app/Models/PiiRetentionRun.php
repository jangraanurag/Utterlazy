<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PiiRetentionRun extends Model
{
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'pii_retention_runs';

    protected $fillable = [
        'actor_user_id',
        'cutoff',
        'dry_run',
        'rows_deleted',
        'breakdown',
        'created_at',
    ];

    protected $casts = [
        'cutoff' => 'datetime',
        'dry_run' => 'boolean',
        'rows_deleted' => 'integer',
        'breakdown' => 'array',
        'created_at' => 'datetime',
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
