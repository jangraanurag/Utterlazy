<?php

namespace App\Services\Analytics;

interface AnalyticsSink
{
    /**
     * Insert rows into a ClickHouse table.
     *
     * @param array<int, array<string, mixed>> $rows
     *
     * @throws \RuntimeException when the sink rejects the batch
     */
    public function insert(string $table, array $rows): void;
}
