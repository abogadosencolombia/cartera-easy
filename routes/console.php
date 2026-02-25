<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ==========================================
// ===== PROGRAMACIÓN DE TAREAS (CRON) ======
// ==========================================

// 1. EL CHISMOSO DE TAREAS VENCIDAS (Nuevo)
// Revisa cada minuto si alguien no cumplió y avisa al admin.
Schedule::command('tareas:check-vencidas')->everyMinute();

// 2. CÁLCULO DE INTERESES (El de tu compañero)
// Configurado a las 03:00 AM para no molestar a nadie.
Schedule::command('app:calculate-late-fees')->dailyAt('03:00');

// 3. ALERTAS PROGRAMADAS (Existente)
// Procesa las alertas del sistema sin superponerse.
Schedule::job(new \App\Jobs\ProcesarAlertasProgramadas)
        ->everyMinute()
        ->withoutOverlapping(55)
        ->name('procesar_alertas_programadas');

// 4. MANTENIMIENTO (Recomendado)
// Limpia tokens de contraseña viejos una vez a la semana
Schedule::command('auth:clear-resets')->weekly();