<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'order_updates', 'offers', 'promotions',
        'new_restaurants', 'rating_reminders',
    ];

    protected $casts = [
        'order_updates' => 'boolean',
        'offers' => 'boolean',
        'promotions' => 'boolean',
        'new_restaurants' => 'boolean',
        'rating_reminders' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
