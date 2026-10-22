<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OpsIncident extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'ops_incidents';

    protected $fillable = [
        'city_id',
        'severity',
        'category',
        'title',
        'description',
        'status',
        'escalation_level',
        'owner_user_id',
        'opened_by',
        'opened_at',
        'ack_at',
        'resolved_at',
        'sla_due_at',
        'sla_breached_at',
        'last_escalated_at',
        'resolution_note',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'ack_at' => 'datetime',
        'resolved_at' => 'datetime',
        'sla_due_at' => 'datetime',
        'sla_breached_at' => 'datetime',
        'last_escalated_at' => 'datetime',
        'escalation_level' => 'integer',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function opener()
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['open', 'ack'], true);
    }
}
