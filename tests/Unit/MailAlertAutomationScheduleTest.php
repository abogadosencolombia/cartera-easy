<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

uses(Tests\TestCase::class);

$emailAlertScheduleNames = [
    'procesar_alertas_programadas',
    'procesar_alertas_gestion_diaria',
    'generar_alertas_juridicas_financieras',
];

$unrelatedScheduleNames = [
    'check_tareas_vencidas',
    'calculate_late_fees',
    'clear_password_resets',
];

function mailAlertScheduleEvent(string $name): Event
{
    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event): bool => $event->description === $name);

    expect($event)->not->toBeNull();

    return $event;
}

it('skips only automated alert email schedules when automation is disabled', function () use ($emailAlertScheduleNames, $unrelatedScheduleNames) {
    config()->set('mail.alerts.automation_enabled', false);

    foreach ($emailAlertScheduleNames as $name) {
        expect(mailAlertScheduleEvent($name)->filtersPass(app()))
            ->toBeFalse();
    }

    foreach ($unrelatedScheduleNames as $name) {
        expect(mailAlertScheduleEvent($name)->filtersPass(app()))
            ->toBeTrue();
    }
});

it('keeps automated alert email schedules enabled when automation is enabled', function () use ($emailAlertScheduleNames, $unrelatedScheduleNames) {
    config()->set('mail.alerts.automation_enabled', true);

    foreach ([...$emailAlertScheduleNames, ...$unrelatedScheduleNames] as $name) {
        expect(mailAlertScheduleEvent($name)->filtersPass(app()))
            ->toBeTrue();
    }
});
