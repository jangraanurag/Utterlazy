<?php

namespace App\Services;

use App\Models\OpsShift;
use App\Models\User;
use Carbon\Carbon;
use DomainException;

class ShiftService
{
    public const MAX_HOURS = 12;

    public const STATUSES = ['scheduled', 'active', 'completed', 'cancelled'];

    public function create(array $data, User $actor): OpsShift
    {
        $start = Carbon::parse($data['starts_at']);
        $end = Carbon::parse($data['ends_at']);

        if ($end->lte($start)) {
            throw new DomainException('Shift end must be after start.');
        }

        if ($start->diffInMinutes($end) > self::MAX_HOURS * 60) {
            throw new DomainException('Shift cannot exceed '.self::MAX_HOURS.' hours.');
        }

        if ($start->isPast() && abs($start->diffInHours(now())) > 24) {
            throw new DomainException('Cannot schedule a shift more than 24 hours in the past.');
        }

        $overlap = OpsShift::where('user_id', $data['user_id'])
            ->whereIn('status', ['scheduled', 'active'])
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->exists();

        if ($overlap) {
            throw new DomainException('This user already has an overlapping shift.');
        }

        return OpsShift::create([
            'user_id' => $data['user_id'],
            'org_unit_id' => $data['org_unit_id'] ?? null,
            'city_id' => $data['city_id'] ?? null,
            'role' => $data['role'] ?? null,
            'starts_at' => $start,
            'ends_at' => $end,
            'status' => 'scheduled',
            'notes' => isset($data['notes']) ? mb_substr((string) $data['notes'], 0, 200) : null,
            'created_by' => $actor->id,
        ]);
    }

    public function activate(OpsShift $shift): OpsShift
    {
        if ($shift->status !== 'scheduled') {
            throw new DomainException('Only a scheduled shift can be activated.');
        }

        if ($shift->ends_at->isPast()) {
            throw new DomainException('Cannot activate a shift that already ended.');
        }

        $shift->update(['status' => 'active']);

        return $shift->fresh();
    }

    public function cancel(OpsShift $shift, string $reason): OpsShift
    {
        if (! in_array($shift->status, ['scheduled', 'active'], true)) {
            throw new DomainException('Only scheduled or active shifts can be cancelled.');
        }

        $reason = trim($reason);
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 200) {
            throw new DomainException('Cancellation reason must be 5-200 characters.');
        }

        $shift->update([
            'status' => 'cancelled',
            'cancelled_reason' => $reason,
        ]);

        return $shift->fresh();
    }

    /**
     * Agents covering a point in time, optionally scoped to a city/org unit.
     *
     * @return array<int, OpsShift>
     */
    public function onShift(?string $cityId = null, ?string $orgUnitId = null, ?Carbon $at = null): array
    {
        $at ??= now();

        return OpsShift::with('user:id,name,admin_role')
            ->whereIn('status', ['scheduled', 'active'])
            ->where('starts_at', '<=', $at)
            ->where('ends_at', '>', $at)
            ->when($cityId, fn ($q, $city) => $q->where('city_id', $city))
            ->when($orgUnitId, fn ($q, $unit) => $q->where('org_unit_id', $unit))
            ->orderBy('starts_at')
            ->get()
            ->all();
    }

    public function coverage(?string $cityId, string $date): array
    {
        $day = Carbon::parse($date);
        $start = $day->copy()->startOfDay();
        $end = $day->copy()->endOfDay();

        $shifts = OpsShift::with('user:id,name,admin_role', 'orgUnit:id,name', 'city:id,name')
            ->whereIn('status', ['scheduled', 'active', 'completed'])
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->when($cityId, fn ($q, $city) => $q->where('city_id', $city))
            ->orderBy('starts_at')
            ->get();

        return [
            'date' => $start->toDateString(),
            'agents' => $shifts->pluck('user_id')->unique()->count(),
            'shifts' => $shifts->count(),
            'items' => $shifts,
        ];
    }
}
