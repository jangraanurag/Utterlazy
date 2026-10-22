<?php

namespace App\Services;

use App\Models\PiiAccessLog;
use App\Models\User;

/**
 * Append-only audit trail for PII views/exports.
 *
 * Logging is mandatory for instrumented exports: if the audit row cannot be
 * written, the export must fail rather than leak unlogged data.
 */
class PiiAccessLogger
{
    public const ACTIONS = ['view', 'export', 'download'];

    public function log(
        User $actor,
        string $action,
        string $resourceType,
        ?string $resourceId = null,
        array $filters = [],
        ?int $rowCount = null,
        ?string $purpose = null,
        array $context = [],
    ): PiiAccessLog {
        if (! in_array($action, self::ACTIONS, true)) {
            throw new \DomainException('Unsupported PII action.');
        }

        return PiiAccessLog::create([
            'actor_user_id' => $actor->id,
            'actor_role' => $actor->admin_role ?? null,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'filters' => $filters,
            'row_count' => $rowCount,
            'purpose' => $purpose ? mb_substr($purpose, 0, 200) : null,
            'ip_address' => $context['ip'] ?? $this->requestIp(),
            'user_agent' => $context['user_agent'] ?? $this->requestUserAgent(),
            'created_at' => now(),
        ]);
    }

    private function requestIp(): ?string
    {
        try {
            return request()->ip();
        } catch (\Throwable) {
            return null;
        }
    }

    private function requestUserAgent(): ?string
    {
        try {
            return mb_substr((string) request()->userAgent(), 0, 255);
        } catch (\Throwable) {
            return null;
        }
    }
}
