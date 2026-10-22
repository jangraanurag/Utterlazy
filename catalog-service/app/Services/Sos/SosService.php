<?php

namespace App\Services\Sos;

use App\Models\Order;
use App\Models\Rider;
use App\Models\SosAlert;
use App\Models\User;
use App\Services\IncidentService;
use App\Services\OutboxPublisher;
use App\Services\Sos\SosPartnerNotifier;
use Carbon\Carbon;
use DomainException;

class SosService
{
    public const DEDUPE_MINUTES = 5;

    public function __construct(
        private readonly OutboxPublisher $outbox,
        private readonly IncidentService $incidents,
        private readonly SosPartnerNotifier $partner,
    ) {
    }

    /**
     * Trigger (or return the recent duplicate of) a rider SOS.
     *
     * Duplicate alerts within 5 minutes return the existing alert and do not
     * create a second incident or partner notification.
     *
     * @return array{alert:SosAlert, deduplicated:bool}
     */
    public function trigger(Rider $rider, array $data): array
    {
        if (! empty($data['order_id'])) {
            $owns = Order::where('id', $data['order_id'])->where('rider_id', $rider->id)->exists();

            if (! $owns) {
                throw new DomainException('Order does not belong to this rider.');
            }
        }

        $existing = SosAlert::where('rider_id', $rider->id)
            ->whereIn('status', ['open', 'ack'])
            ->where('triggered_at', '>=', now()->subMinutes(self::DEDUPE_MINUTES))
            ->latest('triggered_at')
            ->first();

        if ($existing) {
            return ['alert' => $existing, 'deduplicated' => true];
        }

        $alert = SosAlert::create([
            'rider_id' => $rider->id,
            'order_id' => $data['order_id'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'status' => 'open',
            'metadata' => array_filter([
                'note' => isset($data['note']) ? mb_substr((string) $data['note'], 0, 200) : null,
                'battery' => $data['battery'] ?? null,
            ], fn ($v) => $v !== null),
            'triggered_at' => now(),
        ]);

        $incident = $this->incidents->create([
            'severity' => 'sev1',
            'category' => 'safety',
            'title' => 'SOS: rider '.($rider->name ?: substr((string) $rider->id, 0, 8)),
            'description' => 'Rider SOS'
                .($alert->order_id ? ' · order '.$alert->order_id : '')
                .($alert->latitude !== null ? " · {$alert->latitude},{$alert->longitude}" : ''),
        ], $this->safetyOwner());

        if ($incident) {
            $alert->update(['incident_id' => $incident->id]);
        }

        $this->outbox->enqueue('safety_sos', $alert->id, 'safety.sos_triggered', [
            'alert_id' => $alert->id,
            'rider_id' => $alert->rider_id,
            'order_id' => $alert->order_id,
            'latitude' => $alert->latitude,
            'longitude' => $alert->longitude,
            'incident_id' => $incident?->id,
            'triggered_at' => optional($alert->triggered_at)->toIso8601String(),
        ], null);

        $this->partner->notify($alert);

        return ['alert' => $alert->fresh(), 'deduplicated' => false];
    }

    public function acknowledge(SosAlert $alert, ?User $actor): SosAlert
    {
        if ($alert->status !== 'open') {
            throw new DomainException('Only an open SOS can be acknowledged.');
        }

        $alert->update([
            'status' => 'ack',
            'acknowledged_by' => $actor?->id,
            'acknowledged_at' => now(),
        ]);

        return $alert->fresh();
    }

    public function resolve(SosAlert $alert, ?User $actor, string $note): SosAlert
    {
        if (! in_array($alert->status, ['open', 'ack'], true)) {
            throw new DomainException('SOS is already resolved.');
        }

        $note = trim($note);
        if (mb_strlen($note) < 10 || mb_strlen($note) > 500) {
            throw new DomainException('Resolution note must be 10-500 characters.');
        }

        $alert->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolution_note' => $note,
            'acknowledged_by' => $alert->acknowledged_by ?? $actor?->id,
        ]);

        return $alert->fresh();
    }

    public function pending(int $limit = 50): array
    {
        return SosAlert::with(['rider:id,name,phone'])
            ->whereIn('status', ['open', 'ack'])
            ->orderByRaw("FIELD(status, 'open', 'ack')")
            ->orderBy('triggered_at')
            ->limit($limit)
            ->get()
            ->all();
    }

    private function safetyOwner(): ?User
    {
        return User::where('is_admin', true)
            ->where('admin_role', 'rider_ops')
            ->oldest()
            ->first();
    }
}
