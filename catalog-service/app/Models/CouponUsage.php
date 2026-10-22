<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CouponUsage extends Model
{
    use HasUuids;

    protected $fillable = ['coupon_id', 'user_id', 'order_id', 'discount_amount'];

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
