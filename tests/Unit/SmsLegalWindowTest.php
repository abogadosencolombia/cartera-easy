<?php

use App\Services\SmsLegalWindow;
use Carbon\CarbonImmutable;

function bogota(string $dateTime): CarbonImmutable
{
    return CarbonImmutable::parse($dateTime, 'America/Bogota');
}

test('accepts the first conservative window on Monday August 24 2026', function () {
    $result = (new SmsLegalWindow)->evaluate(
        bogota('2026-08-24 07:00:00'),
        bogota('2026-08-20 09:00:00'),
    );

    expect($result)->toMatchArray(['allowed' => true, 'reasons' => []]);
});

test('rejects another channel in the same week and on the same day', function () {
    $window = new SmsLegalWindow;

    expect($window->evaluate(bogota('2026-08-21 08:00:00'), bogota('2026-08-20 09:00:00'))['reasons'])
        ->toContain('contacted_same_week')
        ->and($window->evaluate(bogota('2026-08-20 10:00:00'), bogota('2026-08-20 09:00:00'))['reasons'])
        ->toContain('contacted_today');
});

test('enforces Colombian weekday and Saturday hours', function () {
    $window = new SmsLegalWindow;

    expect($window->evaluate(bogota('2026-08-24 06:59:59'))['allowed'])->toBeFalse()
        ->and($window->evaluate(bogota('2026-08-24 19:00:00'))['allowed'])->toBeFalse()
        ->and($window->evaluate(bogota('2026-08-29 08:00:00'))['allowed'])->toBeTrue()
        ->and($window->evaluate(bogota('2026-08-29 15:00:00'))['allowed'])->toBeFalse()
        ->and($window->evaluate(bogota('2026-08-30 10:00:00'))['allowed'])->toBeFalse();
});

test('rejects configured Colombian holidays', function () {
    $result = (new SmsLegalWindow(['2026-08-24']))->evaluate(bogota('2026-08-24 08:00:00'));

    expect($result['allowed'])->toBeFalse()
        ->and($result['reasons'])->toContain('holiday');
});

test('rejects scheduling in a timezone other than Bogota', function () {
    $result = (new SmsLegalWindow)->evaluate(CarbonImmutable::parse('2026-08-24 07:00:00', 'UTC'));

    expect($result['allowed'])->toBeFalse()
        ->and($result['reasons'])->toContain('invalid_timezone');
});
