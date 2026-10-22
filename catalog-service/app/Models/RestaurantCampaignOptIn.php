<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestaurantCampaignOptIn extends Model
{
    protected $fillable = ['campaign_id', 'restaurant_id', 'opted_in', 'opted_in_at', 'opted_out_at'];

    protected $casts = [
        'opted_in' => 'boolean',
        'opted_in_at' => 'datetime',
        'opted_out_at' => 'datetime',
    ];

    public function campaign()
    {
        return $this->belongsTo(OptInCampaign::class, 'campaign_id');
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }
}
