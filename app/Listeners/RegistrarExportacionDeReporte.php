<?php

namespace App\Listeners;

use App\Events\ReporteExportado;
use App\Models\ExportacionReporte;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class RegistrarExportacionDeReporte
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(ReporteExportado $event): void
    {
        ExportacionReporte::create([
            'user_id' => $event->user->id,
            'tipo_reporte' => $event->tipo,
            'filtros_aplicados' => $event->filtros,
        ]);
    }
}
