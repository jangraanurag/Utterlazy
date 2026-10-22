<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountManagerAssignment extends Model
{
    protected $fillable = ['account_manager_id', 'restaurant_id', 'assigned_by', 'assigned_at', 'unassigned_at', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
        'assigned_at' => 'datetime',
        'unassigned_at' => 'datetime',
    ];

    public function accountManager()
    {
        return $this->belongsTo(User::class, 'account_manager_id');
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
