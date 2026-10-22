<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class StaffShift extends Model
{
    use HasUuids;

    protected $table = 'staff_shifts';

    protected $fillable = [
        'restaurant_id', 'staff_id', 'date', 'start_time', 'end_time',
        'status', 'clocked_in_at', 'clocked_out_at',
    ];

    protected $casts = [
        'date' => 'date',
        'clocked_in_at' => 'datetime',
        'clocked_out_at' => 'datetime',
    ];

    public function staff()
    {
        return $this->belongsTo(RestaurantStaff::class, 'staff_id');
    }
}
