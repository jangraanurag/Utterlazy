<?php

namespace App\Console\Commands;

use App\Services\Analytics\ClickHouseFeatureExporter;
use Illuminate\Console\Command;

class ExportRankerClickHouseFeatures extends Command
{
    protected $signature = 'ranker:export-clickhouse
                            {--as-of= : ISO timestamp (default: now)}
                            {--city= : Optional city UUID}
                            {--out= : CSV output path}
                            {--limit=500}';

    protected $description = 'Export as-of restaurant features from ClickHouse order events (time-travel v1)';

    public function handle(ClickHouseFeatureExporter $exporter): int
    {
        $asOf = $this->option('as-of') ?: now()->toIso8601String();
        $out = $this->option('out') ?: storage_path('app/ranker/clickhouse_'.now()->format('Ymd_His').'.csv');

        if (! is_dir(dirname($out))) {
            mkdir(dirname($out), 0777, true);
        }

        try {
            $rows = $exporter->asOf($asOf, $this->option('city') ?: null, (int) $this->option('limit'));
        } catch (\Throwable $e) {
            $this->error('Export failed: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($rows === []) {
            $this->warn('No ClickHouse events for this as-of/city.');

            return self::SUCCESS;
        }

        file_put_contents($out, $exporter->toCsv($rows, $asOf));
        $this->info('Wrote '.count($rows)." restaurant feature rows to {$out}");

        return self::SUCCESS;
    }
}
