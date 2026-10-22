<?php

namespace App\Services;

use App\Models\OpsIncident;
use App\Models\OpsShift;
use App\Models\OrgRole;
use App\Models\User;
use App\Services\OutboxPublisher;
use DomainException;
use Illuminate\Support\Str;

class IncidentService
{
    public const SEVERITIES = ['sev1', 'sev2', 'sev3'];

    public const CATEGORIES = ['payments', 'riders', 'kitchen', 'whatsapp', 'infra', 'catalog', 'safety', 'staffing', 'other'];

    public const ESCALATION_AT_RISK = 1;

    public const ESCALATION_BREACHED = 2;

    public const SLA_MINUTES = [
        'sev1' => 5,
        'sev2' => 15,
        'sev3' => 60,
    ];

    public function __construct(private readonly OutboxPublisher $outbox)
    {
    }

    public function create(array $data, ?User $actor = null): OpsIncident
    {
        $severity = (string) $data['severity'];
        $title = trim((string) ($data['title'] ?? ''));

        if (! in_array($severity, self::SEVERITIES, true)) {
            throw new DomainException('Unsupported incident severity.');
        }

        if (! in_array((string) $data['category'], self::CATEGORIES, true)) {
            throw new DomainException('Unsupported incident category.');
        }

        if (mb_strlen($title) < 5 || mb_strlen($title) > 160) {
            throw new DomainException('Incident title must be 5-160 characters.');
        }

        $incident = OpsIncident::create([
            'id' => (string) Str::uuid(),
            'city_id' => $data['city_id'] ?? null,
            'severity' => $severity,
            'category' => $data['category'],
            'title' => $title,
            'description' => isset($data['description']) ? mb_substr((string) $data['description'], 0, 2000) : null,
            'status' => 'open',
            'escalation_level' => 0,
            'owner_user_id' => $data['owner_user_id'] ?? null,
            'opened_by' => $actor?->id,
            'opened_at' => now(),
            'sla_due_at' => now()->addMinutes(self::SLA_MINUTES[$severity]),
        ]);

        $this->emit($incident, 'ops_incident.opened', ['severity' => $incident->severity]);

        return $incident;
    }

    public function acknowledge(OpsIncident $incident, User $actor): OpsIncident
    {
        if ($incident->status !== 'open') {
            throw new DomainException('Only an open incident can be acknowledged.');
        }

        $incident->update([
            'status' => 'ack',
            'ack_at' => now(),
            'owner_user_id' => $incident->owner_user_id ?? $actor->id,
        ]);

        $this->emit($incident, 'ops_incident.acknowledged', ['owner_user_id' => $incident->owner_user_id]);

        return $incident->fresh();
    }

    public function resolve(OpsIncident $incident, User $actor, string $note): OpsIncident
    {
        if (! $incident->isOpen()) {
            throw new DomainException('Incident is already resolved.');
        }

        $note = trim($note);
        if (mb_strlen($note) < 10 || mb_strlen($note) > 500) {
            throw new DomainException('Resolution note must be 10-500 characters.');
        }

        $incident->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolution_note' => $note,
            'owner_user_id' => $incident->owner_user_id ?? $actor->id,
        ]);

        $this->emit($incident, 'ops_incident.resolved', []);

        return $incident->fresh();
    }

    public function assign(OpsIncident $incident, string $ownerId, User $actor): OpsIncident
    {
        if (! $incident->isOpen()) {
            throw new DomainException('Cannot assign a resolved incident.');
        }

        $incident->update(['owner_user_id' => $ownerId]);

        $this->emit($incident, 'ops_incident.assigned', ['owner_user_id' => $ownerId]);

        return $incident->fresh();
    }

    /**
     * At-risk escalation: unacknowledged open incidents past 50% of their SLA
     * window get level 1 and a team owner (if none). Emits once per level.
     */
    public function escalateAtRisk(): int
    {
        $incidents = OpsIncident::where('status', 'open')
            ->where('escalation_level', 0)
            ->whereNotNull('sla_due_at')
            ->whereNotNull('opened_at')
            ->limit(500)
            ->get();

        $escalated = 0;

        foreach ($incidents as $incident) {
            $lead = $this->teamOwner('support_lead', $incident->city_id);

            $total = (int) $incident->opened_at->diffInSeconds($incident->sla_due_at, false);
            $elapsed = (int) $incident->opened_at->diffInSeconds(now(), false);

            if ($total <= 0 || $elapsed < ($total / 2) || $elapsed >= $total) {
                continue;
            }

            $incident->update([
                'escalation_level' => self::ESCALATION_AT_RISK,
                'last_escalated_at' => now(),
                'owner_user_id' => $incident->owner_user_id ?? $lead?->id,
            ]);

            $this->emit($incident, 'ops_incident.sla_at_risk', [
                'severity' => $incident->severity,
                'escalation_level' => self::ESCALATION_AT_RISK,
                'target_role' => 'support_lead',
            ]);

            $escalated++;
        }

        return $escalated;
    }

    /**
     * Breach escalation: level 2, team owner if unowned, emitted once.
     */
    public function markBreaches(): int
    {
        $breached = OpsIncident::whereIn('status', ['open', 'ack'])
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<', now())
            ->whereNull('sla_breached_at')
            ->limit(500)
            ->get();

        foreach ($breached as $incident) {
            $lead = $this->teamOwner('support_lead', $incident->city_id);
            $incident->update([
                'sla_breached_at' => now(),
                'escalation_level' => max(self::ESCALATION_BREACHED, (int) $incident->escalation_level),
                'last_escalated_at' => now(),
                'owner_user_id' => $incident->owner_user_id ?? $lead?->id,
            ]);

            $this->emit($incident, 'ops_incident.sla_breached', [
                'severity' => $incident->severity,
                'minutes_late' => (int) abs($incident->sla_due_at->diffInMinutes(now())),
                'escalation_level' => self::ESCALATION_BREACHED,
                'target_role' => 'support_lead',
            ]);
        }

        return $breached->count();
    }

    /**
     * Prefer a team member who is on shift right now for the incident's city,
     * then any admin with the role. This turns the shift roster into on-call.
     */
    private function teamOwner(string $role, ?string $cityId = null): ?User
    {
        $onShift = OpsShift::query()
            ->with('user')
            ->whereIn('status', ['scheduled', 'active'])
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->when($cityId, fn ($q, $city) => $q->where('city_id', $city))
            ->whereHas('user', fn ($q) => $q->where('is_admin', true)->where('admin_role', $role))
            ->orderBy('starts_at')
            ->first();

        if ($onShift?->user) {
            return $onShift->user;
        }

        $backup = OrgRole::where('is_active', true)
            ->whereNotNull('backup_user_id')
            ->whereHas('assignedUser', fn ($q) => $q->where('is_admin', true)->where('admin_role', $role))
            ->with('backupUser')
            ->first()
            ?->backupUser;

        if ($backup && $backup->is_admin) {
            return $backup;
        }

        return User::where('is_admin', true)
            ->where('admin_role', $role)
            ->oldest()
            ->first();
    }

    private function emit(OpsIncident $incident, string $eventType, array $extra): void
    {
        $this->outbox->enqueue(
            'ops_incident',
            $incident->id,
            $eventType,
            array_merge([
                'incident_id' => $incident->id,
                'city_id' => $incident->city_id,
                'severity' => $incident->severity,
                'category' => $incident->category,
                'status' => $incident->status,
            ], $extra),
            $incident->city_id,
        );
    }
}
