<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrgUnit extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'department',
        'unit_type',
        'parent_org_unit_id',
        'geography_scope',
        'name',
        'is_active',
    ];

    protected $casts = [
        'geography_scope' => 'array',
        'is_active' => 'boolean',
    ];

    public function parent()
    {
        return $this->belongsTo(OrgUnit::class, 'parent_org_unit_id');
    }

    public function children()
    {
        return $this->hasMany(OrgUnit::class, 'parent_org_unit_id');
    }

    public function roles()
    {
        return $this->hasMany(OrgRole::class);
    }
}
