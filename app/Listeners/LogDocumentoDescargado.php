<?php

namespace App\Listeners;

use App\Events\DocumentoGeneradoDescargado;
use App\Models\AuditoriaEvento;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class LogDocumentoDescargado
{
    /**
     * Handle the event.
     */
    public function handle(DocumentoGeneradoDescargado $event): void
    {
        AuditoriaEvento::create([
            'user_id' => $event->user->id,
            'evento' => 'DOCUMENTO_DESCARGADO',
            'descripcion_breve' => "Usuario '{$event->user->name}' descargó el documento '{$event->documento->nombre_base}' (Caso #{$event->documento->caso_id}).",
            'auditable_id' => $event->documento->id,
            'auditable_type' => \App\Models\DocumentoGenerado::class,
            'criticidad' => 'media',
            'direccion_ip' => $event->request->ip(),
            'user_agent' => $event->request->userAgent(),
        ]);
    }
}
