<?php

namespace App\Services\Shifts;

use App\Models\City;
use App\Models\OpsIncident;
use App\Models\User;
use App\Services\IncidentService;

/**
 * Turns rotation coverage gaps into owned SEV3 staffing incidents.
 *
 * Gaps are grouped per day/city and deduped by a deterministic title while an
 * incident is still open/ack, so re-applying a rotation does not spam ops.
 */
class RotationGapIncidentService
{
    private IncidentService $incidents;

    public function __construct(?IncidentService $incidents = null)
    {
        $this->incidents = $incidents ?? app(IncidentService::class);
    }

    /** @return array{created:int, skipped:int} */
    public function raise(array $plan, ?User $actor): array
    {
        $gaps = $plan['gaps'] ?? [];

        if ($gaps === []) {
            return ['created' => 0, 'skipped' => 0];
        }

        $owner = User::where('is_admin', true)
            ->where('admin_role', 'support_lead')
            ->oldest()
            ->first();

        $created = 0;
        $skipped = 0;

        foreach ($gaps as $gap) {
            $cityId = $gap['city_id'] ?? null;
            $cityName = $cityId ? (City::find($cityId)?->name ?? substr($cityId, 0, 8)) : 'all cities';
            $title = mb_substr("Staffing gap: {$cityName} on {$gap['date']}", 0, 160);

            $exists = OpsIncident::where('category', 'staffing')
                ->where('title', $title)
                ->whereIn('status', ['open', 'ack'])
                ->exists();

            if ($exists) {
                $skipped++;

                continue;
            }

            $this->incidents->create([
                'severity' => 'sev3',
                'category' => 'staffing',
                'title' => $title,
                'description' => sprintf(
                    'Rotation for week starting %s is missing %d agent(s) in %s.',
                    $plan['week_start'] ?? 'n/a',
                    (int) ($gap['missing'] ?? 0),
                    $cityName,
                ),
                'city_id' => $cityId,
                'owner_user_id' => $owner?->id,
            ], $actor);

            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }
}
