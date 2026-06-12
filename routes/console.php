<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduler principal. EasyPanel solo debe ejecutar: php artisan schedule:run
Schedule::command('tareas:check-vencidas')
    ->everyMinute()
    ->withoutOverlapping()
    ->name('check_tareas_vencidas');

Schedule::command('alertas:procesar-programadas')
    ->everyMinute()
    ->timezone('America/Bogota')
    ->withoutOverlapping(10)
    ->name('procesar_alertas_programadas');

Schedule::command('gestion:procesar-alertas')
    ->everyFiveMinutes()
    ->timezone('America/Bogota')
    ->withoutOverlapping(4)
    ->name('procesar_alertas_gestion_diaria');

Schedule::command('alertas:procesar-vencimientos')
    ->hourly()
    ->between('07:00', '22:00')
    ->timezone('America/Bogota')
    ->withoutOverlapping(59)
    ->name('generar_alertas_juridicas_financieras');

Schedule::command('app:calculate-late-fees')
    ->dailyAt('03:00')
    ->timezone('America/Bogota')
    ->withoutOverlapping()
    ->name('calculate_late_fees');

Schedule::command('auth:clear-resets')
    ->weekly()
    ->name('clear_password_resets');
