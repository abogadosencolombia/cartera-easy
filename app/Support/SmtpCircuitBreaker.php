<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class SmtpCircuitBreaker
{
    private const CACHE_KEY = 'mail_alerts_smtp_cooldown_until';

    public static function active(): bool
    {
        $until = self::until();

        return $until !== null && $until->isFuture();
    }

    public static function until(): ?Carbon
    {
        $value = Cache::get(self::CACHE_KEY);

        if (!$value) {
            return null;
        }

        return $value instanceof CarbonInterface
            ? Carbon::instance($value)
            : Carbon::parse($value);
    }

    public static function trip(string $reason, ?int $minutes = null): Carbon
    {
        $cooldownMinutes = $minutes ?? max(5, (int) config('mail.alerts.cooldown_minutes', 60));
        $until = now()->addMinutes($cooldownMinutes);

        Cache::put(self::CACHE_KEY, $until->toIso8601String(), $until);

        return $until;
    }

    public static function isRateLimited(string $message): bool
    {
        $normalized = mb_strtolower($message);

        return str_contains($normalized, 'ratelimit')
            || str_contains($normalized, 'rate limit')
            || str_contains($normalized, '451')
            || str_contains($normalized, '421')
            || str_contains($normalized, 'timeout')
            || str_contains($normalized, 'timed out');
    }
}
