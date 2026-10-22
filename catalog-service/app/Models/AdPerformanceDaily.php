<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdPerformanceDaily extends Model
{
    protected $table = 'ad_performance_daily';

    protected $fillable = [
        'listing_id', 'date',
        'impressions', 'clicks', 'attributed_orders',
        'spend', 'revenue',
    ];

    protected $casts = [
        'date' => 'date',
        'impressions' => 'integer',
        'clicks' => 'integer',
        'attributed_orders' => 'integer',
        'spend' => 'decimal:2',
        'revenue' => 'decimal:2',
    ];

    public function listing()
    {
        return $this->belongsTo(SponsoredListing::class, 'listing_id');
    }
}
