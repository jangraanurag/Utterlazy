<?php

namespace App\Models;

use App\Services\InvoiceService;
use App\Services\RiderAssignmentService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory, HasUuids;

    protected $appends = [
        'rider_assigned',
        'rider_name',
        'rider_phone',
        'rider_distance_to_restaurant',
        'rider_arrived',
        'handover_deadline_at',
        'timeline',
    ];

    protected $fillable = [
        'user_id',
        'restaurant_id',
        'city_id',
        'address_id',
        'delivery_address_text',
        'rider_id',
        'status',
        'channel',
        'cancel_reason',
        'cancel_note',
        'customer_unreachable_at',
        'delivery_otp',
        'payment_mode',
        'payment_status',
        'refund_attempts',
        'refund_last_error',
        'refund_retried_at',
        'subtotal',
        'tax_amount',
        'vendor_discount_amount',
        'platform_commission',
        'delivery_fee',
        'delivery_fee_customer',
        'delivery_fee_restaurant',
        'delivery_fee_platform',
        'grand_total',
        'rescue_discount_code',
        'rider_assigned_at',
        'rider_picked_up_at',
        'delivered_at',
        'delivery_distance_km',
        'delivery_address_lat',
        'delivery_address_lng',
        'customer_tip',
        'razorpay_order_id',
        'razorpay_payment_id',
        'paid_at',
        'coupon_id',
        'coupon_discount_amount',
        'coins_redeemed',
        'coin_discount_amount',
        'platform_fee',
        'net_payout',
        'accepted_at',
        'prep_time_minutes',
        'prep_deadline_at',
        'ready_marked_at',
        'prep_delay_minutes',
        'rider_arrived_at',
        'handover_delay_minutes',
        'is_business_order',
        'company_name',
        'company_gst',
    ];

    protected $casts = [
        'is_business_order' => 'boolean',
        'rider_assigned_at' => 'datetime',
        'rider_picked_up_at' => 'datetime',
        'delivered_at' => 'datetime',
        'accepted_at' => 'datetime',
        'prep_deadline_at' => 'datetime',
        'ready_marked_at' => 'datetime',
        'rider_arrived_at' => 'datetime',
        'customer_unreachable_at' => 'datetime',
        'refund_retried_at' => 'datetime',
        'refund_attempts' => 'integer',
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function address()
    {
        return $this->belongsTo(Address::class);
    }

    public function invoice()
    {
        return InvoiceService::generate($this);
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function rider()
    {
        return $this->belongsTo(Rider::class);
    }

    public function riderAssignments()
    {
        return $this->hasMany(RiderAssignment::class);
    }

    public function payout()
    {
        return $this->hasOne(RiderPayout::class);
    }

    public function getRiderAssignedAttribute()
    {
        return $this->rider_id !== null;
    }

    public function getRiderNameAttribute()
    {
        return $this->rider ? $this->rider->name : null;
    }

    public function getRiderPhoneAttribute()
    {
        return $this->rider ? $this->rider->phone : null;
    }

    public function getRiderDistanceToRestaurantAttribute()
    {
        $rider = $this->rider;
        $restaurant = $this->restaurant;
        if ($rider && $rider->latitude && $rider->longitude && $restaurant && $restaurant->latitude && $restaurant->longitude) {
            return round(RiderAssignmentService::haversineDistance(
                $rider->latitude, $rider->longitude,
                $restaurant->latitude, $restaurant->longitude
            ), 2);
        }

        return null;
    }

    public function getRiderArrivedAttribute()
    {
        return $this->rider_arrived_at !== null;
    }

    public function getHandoverDeadlineAtAttribute()
    {
        if ($this->ready_marked_at && $this->rider_arrived_at) {
            $handoverStart = max(Carbon::parse($this->ready_marked_at), Carbon::parse($this->rider_arrived_at));

            return $handoverStart->copy()->addMinutes(3)->toIso8601String();
        }

        return null;
    }

    public function getTimelineAttribute()
    {
        $estimatedDelivery = null;
        if ($this->prep_deadline_at) {
            $estimatedDelivery = (new Carbon($this->prep_deadline_at))->addMinutes(20)->toIso8601String();
        }

        return [
            ['step' => 'placed', 'label' => 'Placed', 'time' => $this->created_at, 'completed' => true],
            ['step' => 'accepted', 'label' => 'Accepted', 'time' => $this->accepted_at, 'completed' => $this->accepted_at !== null],
            ['step' => 'estimated_pickup', 'label' => 'Estimated pickup', 'time' => $this->prep_deadline_at, 'completed' => $this->rider_picked_up_at !== null],
            ['step' => 'estimated_delivery', 'label' => 'Estimated delivery', 'time' => $estimatedDelivery, 'completed' => $this->delivered_at !== null],
        ];
    }
}
