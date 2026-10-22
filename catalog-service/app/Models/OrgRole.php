<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrgRole extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'org_unit_id',
        'title',
        'assigned_user_id',
        'backup_user_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function orgUnit()
    {
        return $this->belongsTo(OrgUnit::class);
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function backupUser()
    {
        return $this->belongsTo(User::class, 'backup_user_id');
    }
}
