<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class NotificationCampaign extends Model
{
    use HasUuids;

    protected $fillable = ['title', 'body', 'target_audience', 'target_filters', 'status', 'scheduled_at', 'sent_at', 'sent_count', 'open_count'];

    protected $casts = ['target_filters' => 'array', 'scheduled_at' => 'datetime', 'sent_at' => 'datetime'];
}
