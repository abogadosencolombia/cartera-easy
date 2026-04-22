<?php

namespace App\Listeners;

use App\Events\EventoAuditoriaSospechoso;
use App\Events\ValidacionLegalIncumplida;
use App\Models\IncidenteJuridico;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class RegistrarIncidenteJuridico
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
    public function handle(object $event): void
    {
        if ($event instanceof ValidacionLegalIncumplida) {
            $this->handleValidacionIncumplida($event);
        } elseif ($event instanceof EventoAuditoriaSospechoso) {
            $this->handleEventoAuditoria($event);
        }
    }

    private function handleValidacionIncumplida(ValidacionLegalIncumplida $event): void
    {
        $validacion = $event->validacionLegal;
        $nombreRequisito = $validacion->requisito ? $validacion->requisito->nombre : (\App\Models\ValidacionLegal::TIPOS_VALIDACION[$validacion->tipo] ?? $validacion->tipo);
        
        IncidenteJuridico::create([
            'usuario_responsable_id' => $event->usuario->id,
            'origen' => 'validacion',
            'asunto' => '🔴 Incumplimiento Legal: ' . $nombreRequisito,
            'descripcion' => "Se detectó una falla automática en el requisito \"{$nombreRequisito}\" para el caso #{$validacion->caso_id}. \n\nDetalles registrados: {$validacion->observacion}",
            'estado' => 'pendiente',
            'fecha_registro' => now(),
        ]);
    }

    private function handleEventoAuditoria(EventoAuditoriaSospechoso $event): void
    {
        IncidenteJuridico::create([
            'caso_id' => $event->eventoAuditoria->caso_id ?? null,
            'usuario_responsable_id' => $event->usuario->id,
            'origen' => 'auditoria',
            'asunto' => 'Evento de Auditoría Sospechoso: ' . $event->eventoAuditoria->accion,
            'descripcion' => 'Se registró un evento de auditoría sospechoso. Acción: "' . $event->eventoAuditoria->accion . '". Detalles: ' . $event->eventoAuditoria->descripcion,
            'estado' => 'pendiente',
        ]);
    }
}