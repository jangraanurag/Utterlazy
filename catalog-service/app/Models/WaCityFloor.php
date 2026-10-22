<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaCityFloor extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $primaryKey = 'city_id';

    protected $table = 'wa_city_floors';

    protected $fillable = [
        'city_id',
        'floor_cpc',
        'max_slots',
        'updated_by',
    ];

    protected $casts = [
        'floor_cpc' => 'float',
        'max_slots' => 'integer',
    ];
}
