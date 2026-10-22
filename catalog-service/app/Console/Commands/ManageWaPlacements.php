<?php

namespace App\Console\Commands;

use App\Models\WaPlacement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ManageWaPlacements extends Command
{
    protected $signature = 'wa-placements:manage';

    protected $description = 'Reset daily WhatsApp placement spend and pause/exhaust placements that cannot run';

    public function handle(): int
    {
        $today = now()->toDateString();
        $reset = 0;
        $exhausted = 0;
        $expired = 0;

        // Daily budget rollover: new day resets spend; exhausted → active again.
        WaPlacement::where(function ($q) use ($today) {
            $q->where('spent_date', '!=', $today)->orWhereNull('spent_date');
        })
            ->whereIn('status', [WaPlacement::STATUS_ACTIVE, WaPlacement::STATUS_EXHAUSTED])
            ->chunkById(200, function ($placements) use ($today, &$reset) {
                foreach ($placements as $placement) {
                    $restore = $placement->status === WaPlacement::STATUS_EXHAUSTED;
                    $placement->update([
                        'spent_today' => 0,
                        'spent_date' => $today,
                        'status' => $restore ? WaPlacement::STATUS_ACTIVE : $placement->status,
                    ]);
                    $reset++;
                }
            });

        // Budget exhausted.
        WaPlacement::where('status', WaPlacement::STATUS_ACTIVE)
            ->whereColumn('spent_today', '>=', 'daily_budget')
            ->chunkById(200, function ($placements) use (&$exhausted) {
                foreach ($placements as $placement) {
                    $placement->update(['status' => WaPlacement::STATUS_EXHAUSTED]);
                    $exhausted++;
                }
            });

        // Campaign window closed.
        WaPlacement::where('status', WaPlacement::STATUS_ACTIVE)
            ->where('end_at', '<', now())
            ->chunkById(200, function ($placements) use (&$expired) {
                foreach ($placements as $placement) {
                    $placement->update(['status' => WaPlacement::STATUS_PAUSED]);
                    $expired++;
                }
            });

        if ($reset || $exhausted || $expired) {
            Log::info('WA placements managed', compact('reset', 'exhausted', 'expired'));
            $this->info("reset={$reset} exhausted={$exhausted} expired={$expired}");
        }

        return self::SUCCESS;
    }
}
