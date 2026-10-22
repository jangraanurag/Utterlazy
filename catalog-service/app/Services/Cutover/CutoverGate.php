<?php

namespace App\Services\Cutover;

use App\Models\PricingParityCheck;
use App\Services\Projection\OrderProjectionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Hard gate in front of Wave 2 cutover.
 *
 * The gate is advisory to the router today (traffic is not proxied), but it makes
 * blockers explicit and prevents a premature flag flip from going unnoticed.
 *
 * All checks are blocking. Context injection exists for deterministic tests.
 */
class CutoverGate
{
    public const LOOKBACK_HOURS = 24;

    public const MAX_ARTIFACT_AGE_DAYS = 7;

    public function evaluate(array $context = []): array
    {
        $now = $context['now'] ?? now();
        $flags = $context['flags'] ?? $this->flags();
        $pricing = $context['pricing'] ?? $this->pricing($now);
        $health = $context['health'] ?? $this->javaHealthy();
        $serviceToken = $context['service_token'] ?? (bool) config('services.internal_token');
        $artifact = array_key_exists('artifact', $context) ? $context['artifact'] : $this->certificationArtifact();

        $checks = [];

        $checks[] = $this->check('cutover_flag', $flags['enabled'], 'ORDER_COMMAND_CUTOVER_ENABLED is false.');
        $checks[] = $this->check('cutover_certified', $flags['ready'], 'ORDER_COMMAND_CUTOVER_READY is false.');
        $checks[] = $this->check(
            'allowlist',
            $flags['cities'] !== [] || $flags['restaurants'] !== [],
            'No city or restaurant is allowlisted.',
        );

        $checks[] = $this->check(
            'pricing_samples',
            ($pricing['total'] ?? 0) > 0,
            'No pricing parity checks in the last '.self::LOOKBACK_HOURS.'h.',
        );

        $checks[] = $this->check(
            'pricing_mismatch',
            ($pricing['total'] ?? 0) > 0 && ($pricing['mismatched'] ?? 0) === 0,
            ($pricing['mismatched'] ?? 0).' pricing mismatch(es) in the last '.self::LOOKBACK_HOURS.'h.',
        );

        $checks[] = $this->check('java_health', (bool) $health, 'Java order-command is unreachable.');
        $checks[] = $this->check('service_token', (bool) $serviceToken, 'SERVICE_TOKEN is missing.');

        $projection = $context['projection'] ?? $this->projection();
        $checks[] = $this->check(
            'projection_samples',
            ($projection['last_24h'] ?? 0) > 0,
            'No projected Java orders in the last 24h.',
        );

        try {
            $requireArtifact = (bool) config('services.order_command.require_certification_artifact', false);
        } catch (\Throwable) {
            $requireArtifact = (bool) ($context['require_artifact'] ?? false);
        }
        if ($artifact === null) {
            $checks[] = $this->check(
                'certification_artifact',
                ! $requireArtifact,
                $requireArtifact ? 'No certification artifact found.' : 'No certification artifact (optional).',
            );
        } else {
            $fresh = ($artifact['age_days'] ?? 999) <= self::MAX_ARTIFACT_AGE_DAYS;
            $clean = ($artifact['fails'] ?? 1) === 0;
            $checks[] = $this->check(
                'certification_artifact',
                $fresh && $clean,
                sprintf(
                    'Certification artifact fails=%d age=%sd (max %dd).',
                    $artifact['fails'] ?? -1,
                    $artifact['age_days'] ?? -1,
                    self::MAX_ARTIFACT_AGE_DAYS,
                ),
            );
        }

        $allowed = collect($checks)->every(fn ($c) => $c['ok']);

        return [
            'allowed' => $allowed,
            'checked_at' => $now->toIso8601String(),
            'blockers' => collect($checks)->reject(fn ($c) => $c['ok'])->pluck('detail')->values()->all(),
            'checks' => $checks,
            'flags' => $flags,
        ];
    }

    public function cached(int $seconds = 30): array
    {
        return Cache::remember('cutover:gate', $seconds, fn () => $this->evaluate());
    }

    private function check(string $key, bool $ok, string $detail): array
    {
        return ['key' => $key, 'ok' => $ok, 'detail' => $detail];
    }

    private function flags(): array
    {
        return [
            'enabled' => (bool) config('services.order_command.cutover_enabled', false),
            'ready' => (bool) config('services.order_command.cutover_ready', false),
            'cities' => $this->csv(config('services.order_command.cutover_cities', '')),
            'restaurants' => $this->csv(config('services.order_command.cutover_restaurants', '')),
        ];
    }

    /** @return array{total:int, mismatched:int} */
    private function pricing(Carbon $now): array
    {
        try {
            $base = PricingParityCheck::where('created_at', '>=', $now->copy()->subHours(self::LOOKBACK_HOURS));

            return [
                'total' => (clone $base)->count(),
                'mismatched' => (clone $base)->where('matched', false)->count(),
            ];
        } catch (\Throwable) {
            return ['total' => 0, 'mismatched' => 0];
        }
    }

    /** @return array{last_24h:int, total:int} */
    private function projection(): array
    {
        try {
            return app(OrderProjectionService::class)->status();
        } catch (\Throwable) {
            return ['last_24h' => 0, 'total' => 0];
        }
    }

    private function javaHealthy(): bool
    {
        $url = rtrim((string) config('services.order_command.url', ''), '/');
        if ($url === '') {
            return false;
        }

        try {
            return Http::timeout(2)->get($url.'/api/orders/health')->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array{age_days:int, fails:int}|null */
    private function certificationArtifact(): ?array
    {
        $dir = (string) config('services.order_command.certification_dir', base_path('../infra/certification'));
        $files = glob(rtrim($dir, '/').'/report-*.json') ?: [];

        if ($files === []) {
            return null;
        }

        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        $latest = $files[0];
        $data = json_decode((string) file_get_contents($latest), true);

        if (! is_array($data) || ! isset($data['gates'])) {
            return null;
        }

        $fails = collect($data['gates'])->where('status', 'FAIL')->count();

        return [
            'age_days' => (int) floor((time() - filemtime($latest)) / 86400),
            'fails' => $fails,
        ];
    }

    /** @return string[] */
    private function csv($value): array
    {
        if (is_string($value)) {
            $value = array_map('trim', explode(',', $value));
        }

        return array_values(array_filter((array) $value, fn ($v) => $v !== ''));
    }
}
