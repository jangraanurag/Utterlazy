<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Restaurant extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids;

    protected $fillable = [
        'name', 'email', 'password', 'phone',
        'description', 'logo_url', 'address', 'state', 'district', 'city_id',
        'is_active', 'is_accepting_orders', 'auto_accept_orders',
        'manually_offline', 'paused_until', 'permanent_offline',
        'operating_hours', 'latitude', 'longitude',
        'fssai_number', 'gst_number', 'pan_number',
        'bank_account_number', 'bank_ifsc', 'bank_name', 'bank_holder_name',
        'avg_prep_time_minutes',
        'total_orders_completed', 'total_revenue',
        'commission_rate', 'delivery_fee_share',
        'is_sponsored', 'ad_budget', 'ad_clicks',
        'last_login_at',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'operating_hours' => 'array',
        'is_active' => 'boolean',
        'is_accepting_orders' => 'boolean',
        'auto_accept_orders' => 'boolean',
        'manually_offline' => 'boolean',
        'paused_until' => 'datetime',
        'permanent_offline' => 'boolean',
        'is_sponsored' => 'boolean',
        'ad_budget' => 'float',
        'ad_clicks' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
        'commission_rate' => 'float',
        'delivery_fee_share' => 'float',
        'last_login_at' => 'datetime',
    ];

    public function ratings()
    {
        return $this->hasMany(RestaurantRating::class);
    }

    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    public function menuItems()
    {
        return $this->hasMany(MenuItem::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function packagingSetting()
    {
        return $this->hasOne(RestaurantPackagingSetting::class);
    }

    public function accountManagerAssignments()
    {
        return $this->hasMany(AccountManagerAssignment::class);
    }

    public function currentAccountManager()
    {
        return $this->hasOneThrough(
            User::class,
            AccountManagerAssignment::class,
            'restaurant_id',
            'id',
            'id',
            'account_manager_id'
        )->where('account_manager_assignments.is_active', true);
    }
}
