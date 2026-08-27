<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use DomainException;
use Throwable;

class SmsLegalWindow
{
    public const TIMEZONE = 'America/Bogota';

    /** @param list<string> $holidays Dates formatted as YYYY-MM-DD. */
    public function __construct(private readonly array $holidays = []) {}

    public function parseContactAt(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $value = trim($value);

            if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}) \(Colombia\)$/', $value, $matches) === 1) {
                $parsed = CarbonImmutable::createFromFormat('Y-m-d H:i:s', $matches[1], self::TIMEZONE);

                if ($parsed === false || $parsed->format('Y-m-d H:i:s') !== $matches[1]) {
                    throw new DomainException('El manifiesto contiene una fecha de contacto inválida.');
                }

                return $parsed;
            }

            return CarbonImmutable::parse($value, self::TIMEZONE)->setTimezone(self::TIMEZONE);
        } catch (Throwable) {
            throw new DomainException('El manifiesto contiene una fecha de contacto inválida.');
        }
    }

    /**
     * @return array{allowed: bool, reasons: list<string>}
     */
    public function evaluate(CarbonImmutable $scheduledAt, ?CarbonImmutable $lastContactAt = null): array
    {
        $reasons = [];

        if ($scheduledAt->getTimezone()->getName() !== self::TIMEZONE) {
            $reasons[] = 'invalid_timezone';
        }

        $local = $scheduledAt->setTimezone(self::TIMEZONE);
        $date = $local->format('Y-m-d');
        $day = $local->dayOfWeekIso;
        $time = $local->format('H:i:s');

        if (in_array($date, $this->holidays, true)) {
            $reasons[] = 'holiday';
        }

        if ($day === 7) {
            $reasons[] = 'sunday';
        } elseif ($day === 6) {
            if ($time < '08:00:00' || $time >= '15:00:00') {
                $reasons[] = 'outside_legal_hours';
            }
        } elseif ($time < '07:00:00' || $time >= '19:00:00') {
            $reasons[] = 'outside_legal_hours';
        }

        if ($lastContactAt !== null) {
            $lastLocal = $lastContactAt->setTimezone(self::TIMEZONE);

            if ($lastLocal->format('Y-m-d') === $date) {
                $reasons[] = 'contacted_today';
            }

            if ($lastLocal->format('o-W') === $local->format('o-W')) {
                $reasons[] = 'contacted_same_week';
            }
        }

        $reasons = array_values(array_unique($reasons));

        return [
            'allowed' => $reasons === [],
            'reasons' => $reasons,
        ];
    }
}
