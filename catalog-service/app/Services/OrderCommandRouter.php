<?php

namespace App\Services;

/**
 * Wave 2 cutover decision primitive.
 *
 * NOT wired into the live checkout yet: Java must own pricing/discounts/payments
 * and pass shadow parity first. This class exists so the decision is explicit,
 * testable, and fail-closed when the time comes.
 */
class OrderCommandRouter
{
    public const ROUTE_LARAVEL = 'laravel';
    public const ROUTE_JAVA = 'java';

    /** @param array<string, mixed>|null $options injected in tests */
    public function __construct(private ?array $options = null)
    {
    }

    /**
     * Scope check only: is this city/restaurant inside the certification
     * allowlist? Used by preflight parity even before cutover is certified.
     */
    public function inCutoverScope(?string $cityId, ?string $restaurantId): bool
    {
        if (! $this->flag('cutover_enabled', false)) {
            return false;
        }

        if ($cityId && in_array($cityId, $this->list('cutover_cities'), true)) {
            return true;
        }

        return $restaurantId && in_array($restaurantId, $this->list('cutover_restaurants'), true);
    }

    public function decision(?string $cityId, ?string $restaurantId): array
    {
        if (! $this->flag('cutover_enabled', false)) {
            return $this->result(self::ROUTE_LARAVEL, 'cutover_disabled');
        }

        if (! $this->inCutoverScope($cityId, $restaurantId)) {
            return $this->result(self::ROUTE_LARAVEL, 'not_allowlisted');
        }

        if (! $this->flag('cutover_ready', false)) {
            return $this->result(self::ROUTE_LARAVEL, 'cutover_not_certified');
        }

        $reason = $cityId && in_array($cityId, $this->list('cutover_cities'), true)
            ? 'city_allowlisted'
            : 'restaurant_allowlisted';

        return $this->result(self::ROUTE_JAVA, $reason);
    }

    public function shouldRoute(?string $cityId, ?string $restaurantId): bool
    {
        return $this->decision($cityId, $restaurantId)['route'] === self::ROUTE_JAVA;
    }

    private function result(string $route, string $reason): array
    {
        return [
            'route' => $route,
            'reason' => $reason,
            'shadow' => $this->flag('shadow_enabled', false),
        ];
    }

    private function flag(string $key, bool $default): bool
    {
        $value = $this->option($key, $default);

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /** @return string[] */
    private function list(string $key): array
    {
        $value = $this->option($key, []);

        if (is_string($value)) {
            $value = array_map('trim', explode(',', $value));
        }

        return array_values(array_filter((array) $value, fn ($v) => $v !== ''));
    }

    private function option(string $key, mixed $default): mixed
    {
        if ($this->options !== null) {
            return $this->options[$key] ?? $default;
        }

        return config('services.order_command.'.$key, $default);
    }
}
