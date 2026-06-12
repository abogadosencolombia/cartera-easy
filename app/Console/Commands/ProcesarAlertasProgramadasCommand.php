<?php

namespace App\Console\Commands;

use App\Jobs\ProcesarAlertasProgramadas;
use Illuminate\Console\Command;

class ProcesarAlertasProgramadasCommand extends Command
{
    protected $signature = 'alertas:procesar-programadas';
    protected $description = 'Procesa alertas manuales programadas vencidas y sus recordatorios.';

    public function handle(ProcesarAlertasProgramadas $procesador): int
    {
        $stats = $procesador->handle();

        $this->info(sprintf(
            'Alertas programadas: finales=%d, recordatorios=%d, sin_destinatario=%d, errores=%d',
            $stats['finales_enviadas'] ?? 0,
            $stats['recordatorios_enviados'] ?? 0,
            $stats['sin_destinatario'] ?? 0,
            $stats['errores'] ?? 0,
        ));

        return self::SUCCESS;
    }
}
