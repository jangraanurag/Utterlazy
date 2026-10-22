<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SponsoredListing extends Model
{
    use HasUuids;

    protected $fillable = [
        'restaurant_id', 'ad_type', 'placement',
        'daily_budget', 'current_spend',
        'start_date', 'end_date', 'duration_weeks', 'weekly_budget',
        'priority', 'bid_amount', 'cpc',
        'target_customers', 'time_from', 'time_to', 'all_day',
        'target_roas', 'ctr_estimate', 'cvr_estimate',
        'status', 'approved_at', 'rejection_reason',
        'created_by_type', 'created_by_id',
        'approval_status', 'approved_via',
    ];

    protected $casts = [
        'daily_budget' => 'decimal:2',
        'current_spend' => 'decimal:2',
        'weekly_budget' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'bid_amount' => 'decimal:2',
        'cpc' => 'decimal:2',
        'target_roas' => 'decimal:2',
        'ctr_estimate' => 'decimal:4',
        'cvr_estimate' => 'decimal:4',
        'all_day' => 'boolean',
        'duration_weeks' => 'integer',
        'approved_at' => 'datetime',
    ];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function dailyPerformance()
    {
        return $this->hasMany(AdPerformanceDaily::class, 'listing_id');
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'active')
            ->where('end_date', '>=', now()->toDateString())
            ->whereColumn('current_spend', '<', 'daily_budget');
    }

    public function scopeEligible($q)
    {
        $hour = (float) now()->format('H.i');

        return $q->where('status', 'active')
            ->where('end_date', '>=', now()->toDateString())
            ->where('start_date', '<=', now()->toDateString())
            ->where(function ($t) use ($hour) {
                $t->where('all_day', true)
                    ->orWhere(function ($tw) use ($hour) {
                        $tw->where('time_from', '<=', date('H:i', (int) ($hour * 3600)))
                            ->where('time_to', '>=', date('H:i', (int) ($hour * 3600)));
                    });
            });
    }
}
