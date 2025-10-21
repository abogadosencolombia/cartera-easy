<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Tarea existente para procesar alertas
        $schedule->job(new \App\Jobs\ProcesarAlertasProgramadas)
                 ->everyMinute()
                 ->withoutOverlapping(55)
                 ->name('procesar_alertas_programadas');

        // --- NUEVA TAREA AÑADIDA ---
        // Tarea para calcular los intereses de mora diariamente
        $schedule->command('app:calculate-late-fees')
                 ->dailyAt('12:00') // Se ejecutará todos los días a la 12:00 M
                 ->name('calculate_late_fees');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}

