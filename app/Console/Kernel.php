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
        // Se cambia de dailyAt('08:00') a everyThirtyMinutes() para que procese los lotes (batch) de 20
        // que quedan pendientes por el límite de ráfaga de Hostinger.
        $schedule->command('alertas:procesar-vencimientos')
                  ->everyThirtyMinutes()
                  ->between('07:00', '22:00')
                  ->timezone('America/Bogota')
                  ->name('generar_alertas_juridicas_financieras');

        // NUEVA GESTIÓN DIARIA: Procesar alertas cada 15 minutos
        $schedule->command('gestion:procesar-alertas')
                  ->everyFifteenMinutes()
                  ->timezone('America/Bogota')
                  ->name('procesar_alertas_gestion_diaria');    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}