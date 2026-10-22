<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Outbox extends Model
{
    use HasFactory, HasUuids;

    public const CREATED_AT = 'occurred_at';

    public const UPDATED_AT = null;

    protected $table = 'outbox';

    protected $fillable = [
        'event_id',
        'aggregate_type',
        'aggregate_id',
        'event_type',
        'payload',
        'city_id',
        'occurred_at',
        'published_at',
        'attempts',
        'last_error',
    ];

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function markPublished(): void
    {
        $this->update(['published_at' => now(), 'last_error' => null]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'attempts' => $this->attempts + 1,
            'last_error' => $error,
        ]);
    }
}
