<?php

namespace App\Console\Commands;

use App\Models\RiderAssignment;
use App\Models\SystemConfig;
use App\Services\Rider\ExplorationTuner;
use Illuminate\Console\Command;

class TuneRiderExploration extends Command
{
    protected $signature = 'riders:tune-exploration
                            {--days=7 : Assignment lookback}
                            {--target=12.5 : Target share of assignments to <20-trip riders}
                            {--apply : Persist rider_exploration_every to SystemConfig}';

    protected $description = 'Recommend (and optionally apply) the new-rider exploration frequency from assignment data';

    public function handle(ExplorationTuner $tuner): int
    {
        $since = now()->subDays(max(1, (int) $this->option('days')));

        $assignments = RiderAssignment::with('rider:id,total_deliveries')
            ->where('created_at', '>=', $since)
            ->get();

        $total = $assignments->count();
        $new = $assignments->filter(
            fn (RiderAssignment $a) => $a->rider !== null && (int) $a->rider->total_deliveries < 20
        )->count();

        $rec = $tuner->recommend($total, $new, (float) $this->option('target'));

        $this->info(sprintf(
            'Assignments: %d · new-rider: %d (%.2f%%) · target %.2f%% · recommend every %d%s',
            $rec['total'],
            $rec['new_riders'],
            $rec['current_pct'],
            $rec['target_pct'],
            $rec['every'],
            $rec['insufficient_data'] ? ' (insufficient data — baseline)' : '',
        ));

        if ($this->option('apply')) {
            SystemConfig::setValue('rider_exploration_every', (string) $rec['every']);
            $this->info("Applied rider_exploration_every={$rec['every']}.");
        } else {
            $this->line('Dry-run. Re-run with --apply to persist.');
        }

        return self::SUCCESS;
    }
}
