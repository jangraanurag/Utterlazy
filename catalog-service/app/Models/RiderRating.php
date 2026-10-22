<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiderRating extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'rider_id', 'order_id', 'user_id', 'rating', 'feedback', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function rider()
    {
        return $this->belongsTo(Rider::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
