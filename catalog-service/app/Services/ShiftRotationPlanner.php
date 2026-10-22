<?php

namespace App\Services;

use App\Models\OpsShift;
use App\Models\User;
use App\Models\WaWorkforce;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds a deterministic weekly rotation from active workforce entries.
 *
 * Guarantees:
 * - no overlapping shift for the same agent (existing or newly planned),
 * - at most one shift per agent per day when per_day=1 (never double-books a person),
 * - max 12h per shift,
 * - dry-run by default at the command/API layer; apply re-checks overlaps in a transaction.
 *
 * Insufficient coverage is reported as gaps rather than silently understaffing.
 */
class ShiftRotationPlanner
{
    public const MAX_HOURS = 12;

    /**
     * @return array{week_start:string, entries:array<int,array<string,mixed>>, gaps:array<int,array<string,mixed>>}
     */
    public function plan(Carbon $weekStart, array $options = []): array
    {
        $days = max(1, min(7, (int) ($options['days'] ?? 7)));
        $perDay = max(1, min(20, (int) ($options['per_day'] ?? 1)));
        $hours = max(1, min(self::MAX_HOURS, (int) ($options['hours'] ?? 8)));
        $startTime = (string) ($options['start_time'] ?? '09:00');
        $cityId = $options['city_id'] ?? null;
        $orgUnitId = $options['org_unit_id'] ?? null;

        $weekStart = $weekStart->copy()->startOfDay();
        $weekEnd = $weekStart->copy()->addDays($days);

        $agents = WaWorkforce::query()
            ->where('active', true)
            ->when($cityId, fn ($q, $city) => $q->where('city_id', $city))
            ->orderBy('user_id')
            ->get()
            ->filter(fn (WaWorkforce $row) => $row->user !== null)
            ->values();

        $existing = OpsShift::whereIn('status', ['scheduled', 'active', 'completed'])
            ->where('starts_at', '<', $weekEnd)
            ->where('ends_at', '>', $weekStart)
            ->get()
            ->groupBy('user_id');

        $entries = [];
        $gaps = [];

        for ($day = 0; $day < $days; $day++) {
            $date = $weekStart->copy()->addDays($day);
            $start = $date->copy()->setTimeFromTimeString($startTime);
            $end = $start->copy()->addMinutes($hours * 60);

            if ($agents->isEmpty()) {
                $gaps[] = $this->gap($date, $cityId, $perDay);

                continue;
            }

            $assignedToday = [];
            $needed = $perDay;
            $offsetBase = ($day * $perDay) % $agents->count();

            for ($i = 0; $i < $agents->count() && $needed > 0; $i++) {
                $agent = $agents[($offsetBase + $i) % $agents->count()];
                $userId = (string) $agent->user_id;

                if (isset($assignedToday[$userId])) {
                    continue;
                }

                if ($this->overlaps($existing->get($userId, collect()), $start, $end)) {
                    continue;
                }

                $entries[] = [
                    'user_id' => $userId,
                    'city_id' => $agent->city_id,
                    'org_unit_id' => $orgUnitId,
                    'role' => $agent->user?->admin_role,
                    'starts_at' => $start->toIso8601String(),
                    'ends_at' => $end->toIso8601String(),
                    'notes' => 'auto-rotation',
                ];

                $assignedToday[$userId] = true;
                $needed--;
            }

            if ($needed > 0) {
                $gaps[] = $this->gap($date, $cityId, $needed);
            }
        }

        return [
            'week_start' => $weekStart->toDateString(),
            'entries' => $entries,
            'gaps' => $gaps,
        ];
    }

    /** @return array{created:int, skipped:int} */
    public function apply(array $plan, ?User $actor): array
    {
        $created = 0;
        $skipped = 0;

        (new OpsShift)->getConnection()->transaction(function () use ($plan, $actor, &$created, &$skipped) {
            foreach ($plan['entries'] as $entry) {
                $start = Carbon::parse($entry['starts_at']);
                $end = Carbon::parse($entry['ends_at']);

                $overlap = OpsShift::where('user_id', $entry['user_id'])
                    ->whereIn('status', ['scheduled', 'active', 'completed'])
                    ->where('starts_at', '<', $end)
                    ->where('ends_at', '>', $start)
                    ->exists();

                if ($overlap) {
                    $skipped++;

                    continue;
                }

                OpsShift::create([
                    'user_id' => $entry['user_id'],
                    'org_unit_id' => $entry['org_unit_id'] ?? null,
                    'city_id' => $entry['city_id'] ?? null,
                    'role' => $entry['role'] ?? null,
                    'starts_at' => $start,
                    'ends_at' => $end,
                    'status' => 'scheduled',
                    'notes' => $entry['notes'] ?? 'auto-rotation',
                    'created_by' => $actor?->id,
                ]);

                $created++;
            }
        });

        return ['created' => $created, 'skipped' => $skipped];
    }

    private function overlaps(Collection $shifts, Carbon $start, Carbon $end): bool
    {
        return $shifts->contains(
            fn (OpsShift $shift) => $shift->starts_at->lt($end) && $shift->ends_at->gt($start)
        );
    }

    private function gap(Carbon $date, ?string $cityId, int $missing): array
    {
        return [
            'date' => $date->toDateString(),
            'city_id' => $cityId,
            'missing' => $missing,
        ];
    }
}
