<?php

namespace App\Console\Commands;

use App\Models\PricingParityCheck;
use Illuminate\Console\Command;

class PricingParityReport extends Command
{
    protected $signature = 'pricing:parity-report {--hours=24} {--limit=20}';

    protected $description = 'Show Java vs Laravel pricing parity for cutover certification';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $limit = (int) $this->option('limit');
        $base = PricingParityCheck::where('created_at', '>=', now()->subHours($hours));

        $total = (clone $base)->count();
        $matched = (clone $base)->where('matched', true)->count();
        $mismatched = $total - $matched;

        $this->info(sprintf(
            'Pricing parity (%dh): total=%d matched=%d mismatched=%d rate=%.3f%%',
            $hours,
            $total,
            $matched,
            $mismatched,
            $total > 0 ? ($mismatched / $total) * 100 : 0,
        ));

        if ($mismatched === 0) {
            $this->info('No mismatches in the window.');

            return self::SUCCESS;
        }

        $rows = (clone $base)->where('matched', false)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        foreach ($rows as $row) {
            $diffs = collect($row->diffs ?? [])
                ->map(fn ($d, $k) => is_array($d)
                    ? "{$k}: laravel={$d['laravel']} java={$d['java']}"
                    : "{$k}: {$d}")
                ->implode(' | ');

            $this->warn("#{$row->order_id} {$row->created_at}: {$diffs}");
        }

        return $mismatched > 0 ? self::FAILURE : self::SUCCESS;
    }
}
