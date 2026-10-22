<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class GroupOrder extends Model
{
    use HasUuids;

    protected $fillable = [
        'host_user_id', 'restaurant_id', 'invite_code',
        'scheduled_at', 'status', 'participant_count',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
    ];

    public function host()
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function items()
    {
        return $this->hasMany(GroupOrderItem::class);
    }
}
