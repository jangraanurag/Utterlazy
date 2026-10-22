<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Rider extends Authenticatable
{
    use HasApiTokens, HasUuids;

    protected $fillable = [
        'name', 'email', 'phone', 'password',
        'vehicle_type', 'vehicle_number', 'profile_photo',
        'latitude', 'longitude', 'is_online', 'is_verified',
        'verification_status', 'verification_notes',
        'current_order_id', 'avg_rating', 'total_deliveries',
        'total_earnings', 'acceptance_rate', 'badges', 'rider_tier',
        'emergency_contacts', 'safety_ride_sharing_enabled', 'share_link', 'share_expires_at',
        'preferred_zones', 'upi_id', 'delivery_mode',
        'state', 'district',
        'deactivated_at', 'deactivation_reason',
        'gps_anomaly_count', 'gps_flagged_at',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'is_online' => 'boolean',
        'is_verified' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
        'avg_rating' => 'float',
        'acceptance_rate' => 'float',
        'badges' => 'array',
        'emergency_contacts' => 'array',
        'safety_ride_sharing_enabled' => 'boolean',
        'share_expires_at' => 'datetime',
        'deactivated_at' => 'datetime',
        'gps_flagged_at' => 'datetime',
        'gps_anomaly_count' => 'integer',
        'preferred_zones' => 'array',
        'password' => 'hashed',
    ];

    // ─── Relationships ───

    public function currentOrder()
    {
        return $this->belongsTo(Order::class, 'current_order_id');
    }

    public function assignments()
    {
        return $this->hasMany(RiderAssignment::class);
    }

    public function payouts()
    {
        return $this->hasMany(RiderPayout::class);
    }

    public function ratings()
    {
        return $this->hasMany(RiderRating::class);
    }

    public function documents()
    {
        return $this->hasMany(RiderDocument::class);
    }

    // ─── Helpers ───

    public function todaysDeliveries(): int
    {
        return RiderPayout::where('rider_id', $this->id)
            ->whereDate('created_at', today())
            ->count();
    }

    public function todaysEarnings(): float
    {
        return (float) RiderPayout::where('rider_id', $this->id)
            ->whereDate('created_at', today())
            ->sum('total_payout');
    }

    public function unsettledAmount(): float
    {
        return (float) $this->payouts()->where('is_settled', false)->sum('total_payout');
    }

    /**
     * Recalculate average rating from all ratings.
     */
    public function recalculateRating(): void
    {
        $avg = $this->ratings()->avg('rating');
        if ($avg !== null) {
            $this->update(['avg_rating' => round($avg, 2)]);
        }
    }

    /**
     * Recalculate acceptance rate from recent 50 assignments.
     */
    public function recalculateAcceptanceRate(): void
    {
        $recent = $this->assignments()
            ->whereIn('status', ['accepted', 'rejected', 'expired'])
            ->orderBy('created_at', 'desc')
            ->take(50)
            ->get();

        if ($recent->count() > 0) {
            $accepted = $recent->where('status', 'accepted')->count();
            $rate = ($accepted / $recent->count()) * 100;
            $this->update(['acceptance_rate' => round($rate, 2)]);
        }
    }
}
