<?php

namespace App\Services\Rider;

class ExplorationTuner
{
    /**
     * Recommend how often to prefer new riders (every N assignments) from the
     * observed share of assignments going to <20-trip riders.
     *
     * Target default 12.5% ≈ 1 in 8. Low share → explore more (every 4);
     * high share → back off (every 16).
     *
     * @return array{every:int,current_pct:float,target_pct:float,insufficient_data:bool,total:int,new_riders:int}
     */
    public function recommend(int $totalAssignments, int $newRiderAssignments, float $targetPct = 12.5): array
    {
        $target = max(1.0, min(50.0, $targetPct));
        $total = max(0, $totalAssignments);
        $new = max(0, min($total, $newRiderAssignments));

        $current = $total > 0 ? ($new / $total) * 100 : 0.0;
        $base = max(1, min(50, (int) round(100 / $target)));

        if ($total < 50) {
            $every = $base;
        } elseif ($current < $target * 0.8) {
            $every = max(2, (int) floor($base / 2));
        } elseif ($current > $target * 1.2) {
            $every = min(50, $base * 2);
        } else {
            $every = $base;
        }

        return [
            'every' => $every,
            'current_pct' => round($current, 2),
            'target_pct' => round($target, 2),
            'insufficient_data' => $total < 50,
            'total' => $total,
            'new_riders' => $new,
        ];
    }
}
