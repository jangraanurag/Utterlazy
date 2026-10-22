<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaPlacement extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_EXHAUSTED = 'exhausted';
    public const STATUS_REJECTED = 'rejected';

    protected $table = 'wa_placements';

    protected $fillable = [
        'restaurant_id',
        'city_id',
        'daily_budget',
        'cpc_bid',
        'spent_today',
        'spent_date',
        'start_at',
        'end_at',
        'creative_title',
        'creative_body',
        'status',
        'pinned_floor',
        'approval_instance_id',
        'rejection_reason',
    ];

    protected $casts = [
        'daily_budget' => 'float',
        'cpc_bid' => 'float',
        'spent_today' => 'float',
        'pinned_floor' => 'float',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'spent_date' => 'date',
    ];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function hasRemainingBudget(): bool
    {
        return $this->spent_today < $this->daily_budget;
    }

    public function isRunnable(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->start_at->isPast()
            && $this->end_at->isFuture()
            && $this->hasRemainingBudget();
    }
}
