<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OpsShift extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'ops_shifts';

    protected $fillable = [
        'user_id',
        'org_unit_id',
        'city_id',
        'role',
        'starts_at',
        'ends_at',
        'status',
        'notes',
        'cancelled_reason',
        'created_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orgUnit()
    {
        return $this->belongsTo(OrgUnit::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function isActiveNow(): bool
    {
        return $this->status === 'active'
            && $this->starts_at->lte(now())
            && $this->ends_at->gt(now());
    }
}
