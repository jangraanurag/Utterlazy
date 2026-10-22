<?php

namespace App\Services;

use App\Models\PiiAccessLog;
use App\Models\PiiRetentionRun;
use App\Models\User;
use Carbon\Carbon;
use DomainException;

/**
 * Manual, audited retention for PII access logs.
 *
 * - Minimum window is 30 days.
 * - Dry-run by default; deletion requires an explicit confirmation upstream.
 * - Safety evidence (SOS, incidents, orders) is never touched here.
 */
class PiiRetentionService
{
    public const MIN_DAYS = 30;

    public const DELETE_CHUNK = 1000;

    public function cutoff(int $days): Carbon
    {
        if ($days < self::MIN_DAYS) {
            throw new DomainException('Retention window must be at least '.self::MIN_DAYS.' days.');
        }

        return now()->subDays($days);
    }

    /** @return array{total:int, by_action:array<string,int>, by_resource:array<string,int>} */
    public function preview(Carbon $cutoff): array
    {
        $byAction = PiiAccessLog::where('created_at', '<', $cutoff)
            ->selectRaw('action, COUNT(*) AS c')
            ->groupBy('action')
            ->pluck('c', 'action')
            ->map(fn ($c) => (int) $c)
            ->all();

        $byResource = PiiAccessLog::where('created_at', '<', $cutoff)
            ->selectRaw('resource_type, COUNT(*) AS c')
            ->groupBy('resource_type')
            ->pluck('c', 'resource_type')
            ->map(fn ($c) => (int) $c)
            ->all();

        return [
            'total' => array_sum($byAction),
            'by_action' => $byAction,
            'by_resource' => $byResource,
        ];
    }

    /**
     * @return array{dry_run:bool, cutoff:string, breakdown:array, rows_deleted:int, run_id:string}
     */
    public function purge(Carbon $cutoff, ?User $actor, bool $dryRun = true): array
    {
        $breakdown = $this->preview($cutoff);
        $deleted = 0;

        if (! $dryRun) {
            while (true) {
                $ids = PiiAccessLog::where('created_at', '<', $cutoff)
                    ->limit(self::DELETE_CHUNK)
                    ->pluck('id');

                if ($ids->isEmpty()) {
                    break;
                }

                $deleted += PiiAccessLog::whereIn('id', $ids)->delete();
            }
        }

        $run = PiiRetentionRun::create([
            'actor_user_id' => $actor?->id,
            'cutoff' => $cutoff,
            'dry_run' => $dryRun,
            'rows_deleted' => $deleted,
            'breakdown' => $breakdown,
        ]);

        return [
            'dry_run' => $dryRun,
            'cutoff' => $cutoff->toIso8601String(),
            'breakdown' => $breakdown,
            'rows_deleted' => $deleted,
            'run_id' => $run->id,
        ];
    }
}
