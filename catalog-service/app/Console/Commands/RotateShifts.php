<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ShiftRotationPlanner;
use App\Services\Shifts\RotationGapIncidentService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RotateShifts extends Command
{
    protected $signature = 'shifts:rotate
                            {--week-start= : YYYY-MM-DD (defaults to next Monday)}
                            {--city= : City UUID}
                            {--org-unit= : Org unit UUID applied to all entries}
                            {--start-time=09:00 : Shift start (H:i)}
                            {--hours=8 : Shift length (1-12)}
                            {--per-day=1 : Agents per day}
                            {--days=7 : Days to plan (1-7)}
                            {--apply : Create the shifts (default is dry-run)}
                            {--actor= : Admin email recorded as creator}
                            {--fail-on-gap : Exit non-zero when coverage gaps exist}';

    protected $description = 'Plan (and optionally apply) a weekly ops rotation from active workforce';

    public function handle(ShiftRotationPlanner $planner, RotationGapIncidentService $gapIncidents): int
    {
        $weekStart = $this->option('week-start')
            ? Carbon::parse($this->option('week-start'))
            : now()->startOfWeek(Carbon::MONDAY)->addWeek();

        $options = [
            'city_id' => $this->option('city') ?: null,
            'org_unit_id' => $this->option('org-unit') ?: null,
            'start_time' => $this->option('start-time'),
            'hours' => (int) $this->option('hours'),
            'per_day' => (int) $this->option('per-day'),
            'days' => (int) $this->option('days'),
        ];

        $plan = $planner->plan($weekStart, $options);

        $this->info(sprintf(
            'Rotation %s → %d entries, %d gap day(s)',
            $plan['week_start'],
            count($plan['entries']),
            count($plan['gaps']),
        ));

        $this->table(
            ['Date/time', 'Agent', 'City', 'Role'],
            collect($plan['entries'])->map(fn ($e) => [
                Carbon::parse($e['starts_at'])->format('D d M H:i').'–'.Carbon::parse($e['ends_at'])->format('H:i'),
                $e['user_id'],
                $e['city_id'] ?? '-',
                $e['role'] ?? '-',
            ])->all(),
        );

        foreach ($plan['gaps'] as $gap) {
            $this->warn("GAP {$gap['date']}: missing {$gap['missing']} agent(s)");
        }

        if ($this->option('apply')) {
            $actor = null;
            if ($email = $this->option('actor')) {
                $actor = User::where('email', $email)->first();
                if (! $actor) {
                    $this->error("Actor not found: {$email}");

                    return self::FAILURE;
                }
            }

            $result = $planner->apply($plan, $actor);
            $this->info("Applied: created={$result['created']} skipped={$result['skipped']}");

            if ($plan['gaps'] !== []) {
                $incidents = $gapIncidents->raise($plan, $actor);
                $this->warn("Staffing incidents: created={$incidents['created']} skipped={$incidents['skipped']}");
            }
        } else {
            $this->warn('Dry-run only. Re-run with --apply to create these shifts.');
        }

        return $this->option('fail-on-gap') && $plan['gaps'] !== [] ? self::FAILURE : self::SUCCESS;
    }
}
