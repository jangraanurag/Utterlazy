<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApprovalWorkflowInstance extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'department',
        'entity_type',
        'entity_id',
        'origin_org_unit_id',
        'current_org_unit_id',
        'current_org_role_id',
        'status',
        'amount',
        'is_exception',
        'threshold_snapshot',
        'pinned_max_amount',
        'pinned_sla_hours',
        'sla_due_at',
        'completed_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'is_exception' => 'boolean',
        'threshold_snapshot' => 'array',
        'pinned_max_amount' => 'float',
        'pinned_sla_hours' => 'integer',
        'sla_due_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function originOrgUnit()
    {
        return $this->belongsTo(OrgUnit::class, 'origin_org_unit_id');
    }

    public function currentOrgUnit()
    {
        return $this->belongsTo(OrgUnit::class, 'current_org_unit_id');
    }

    public function currentOrgRole()
    {
        return $this->belongsTo(OrgRole::class, 'current_org_role_id');
    }

    public function steps()
    {
        return $this->hasMany(ApprovalWorkflowStep::class, 'workflow_instance_id')->orderBy('created_at');
    }
}
