<?php

namespace App\Console\Commands;

use App\Services\Cutover\CutoverGate;
use Illuminate\Console\Command;

class CheckCutover extends Command
{
    protected $signature = 'cutover:check {--strict : Fail when any blocker exists, even if cutover flags are off}';

    protected $description = 'Evaluate Wave 2 cutover readiness (flags, pricing parity, Java health, certification)';

    public function handle(CutoverGate $gate): int
    {
        $result = $gate->evaluate();

        $this->table(
            ['Check', 'Status', 'Detail'],
            collect($result['checks'])->map(fn ($c) => [$c['key'], $c['ok'] ? 'PASS' : 'BLOCK', $c['detail']])->all(),
        );

        if ($result['allowed']) {
            $this->info('Cutover gate: READY');

            return self::SUCCESS;
        }

        $this->warn('Cutover gate: BLOCKED');
        foreach ($result['blockers'] as $blocker) {
            $this->line('  - '.$blocker);
        }

        $enabled = (bool) ($result['flags']['enabled'] ?? false);

        return ($enabled || $this->option('strict')) ? self::FAILURE : self::SUCCESS;
    }
}
