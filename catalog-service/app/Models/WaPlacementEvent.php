<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaPlacementEvent extends Model
{
    use HasFactory, HasUuids;

    public const TYPE_IMPRESSION = 'impression';

    public const TYPE_CLICK = 'click';

    public $timestamps = false;

    protected $table = 'wa_placement_events';

    protected $fillable = [
        'placement_id',
        'city_id',
        'event_type',
        'external_id',
        'amount',
        'occurred_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'occurred_at' => 'datetime',
    ];
}
