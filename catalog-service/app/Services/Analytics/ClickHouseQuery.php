<?php

namespace App\Services\Analytics;

interface ClickHouseQuery
{
    /**
     * Run a SELECT and return decoded rows.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws \RuntimeException on transport or ClickHouse errors
     */
    public function rows(string $sql): array;
}
