<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectedOrder extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $primaryKey = 'order_id';

    protected $table = 'projected_orders';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'pricing' => 'array',
        'grand_total' => 'float',
        'version' => 'integer',
        'last_event_at' => 'datetime',
        'projected_at' => 'datetime',
    ];
}
