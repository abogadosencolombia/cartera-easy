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
        // Tarea existente para procesar alertas (Esta corre cada minuto, no necesita zona horaria)
        $schedule->job(new \App\Jobs\ProcesarAlertasProgramadas)
                 ->everyMinute()
                 ->withoutOverlapping(55)
                 ->name('procesar_alertas_programadas');

        // Tarea para calcular los intereses de mora diariamente
        $schedule->command('app:calculate-late-fees')
                 ->dailyAt('12:00')
                 ->timezone('America/Bogota') // <--- AGREGADO: Para que sea al mediodía COLOMBIA
                 ->name('calculate_late_fees');

        // --- TAREA DE NOTIFICACIONES ---
        $schedule->command('alertas:procesar-vencimientos')
                 ->dailyAt('08:00')
                 ->timezone('America/Bogota') // <--- LA SOLUCIÓN: Hora de Colombia
                 ->name('generar_alertas_juridicas_financieras');
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