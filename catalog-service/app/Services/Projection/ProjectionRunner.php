<?php

namespace App\Services\Projection;

use Illuminate\Support\Facades\Log;

class ProjectionRunner
{
    /** @return array{read:int, applied:int, failed:int} */
    public function run(OrderEventSource $source, OrderProjectionService $projection, int $limit, int $timeoutMs): array
    {
        $read = 0;
        $applied = 0;

        foreach ($source->poll($limit, $timeoutMs) as $record) {
            $read++;

            try {
                $outcome = $projection->apply($record['envelope']);
            } catch (\Throwable $e) {
                try {
                    Log::error('Order projection failed.', [
                        'record' => $record['id'],
                        'error' => $e->getMessage(),
                    ]);
                } catch (\Throwable) {
                    // standalone tests without a facade root
                }

                return ['read' => $read, 'applied' => $applied, 'failed' => 1];
            }

            $source->ack($record);

            if ($outcome['applied']) {
                $applied++;
            }
        }

        return ['read' => $read, 'applied' => $applied, 'failed' => 0];
    }
}
