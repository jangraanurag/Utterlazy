<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DeliveryPass extends Model
{
    use HasUuids;

    protected $table = 'delivery_passes';

    protected $fillable = [
        'user_id', 'type', 'price', 'valid_from', 'valid_until', 'status',
    ];
}
