<?php

use App\Services\UserWorkSessionService;
use Illuminate\Support\Carbon;

test('session elapsed seconds are floored to an integer', function () {
    $service = new UserWorkSessionService();
    $method = new ReflectionMethod($service, 'secondsBetween');
    $method->setAccessible(true);

    $from = Carbon::parse('2026-06-10 10:02:59');
    $to = Carbon::parse('2026-06-10 10:20:43.387239');

    expect($method->invoke($service, $from, $to))->toBe(1064);
});
