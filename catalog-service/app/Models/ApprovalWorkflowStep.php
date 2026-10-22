<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApprovalWorkflowStep extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'workflow_instance_id',
        'org_unit_id',
        'org_role_id',
        'actor_user_id',
        'action',
        'comment',
        'to_org_unit_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function workflowInstance()
    {
        return $this->belongsTo(ApprovalWorkflowInstance::class, 'workflow_instance_id');
    }

    public function orgUnit()
    {
        return $this->belongsTo(OrgUnit::class);
    }

    public function toOrgUnit()
    {
        return $this->belongsTo(OrgUnit::class, 'to_org_unit_id');
    }

    public function actorUser()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
