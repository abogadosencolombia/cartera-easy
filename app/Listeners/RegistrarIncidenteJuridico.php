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
        IncidenteJuridico::create([
            'caso_id' => $event->validacionLegal->caso_id ?? null,
            'usuario_responsable_id' => $event->usuario->id,
            'origen' => 'validacion',
            'asunto' => 'Incumplimiento de Validación Legal: ' . $event->validacionLegal->requisito->nombre,
            'descripcion' => 'Se detectó un incumplimiento en la validación del requisito "' . $event->validacionLegal->requisito->nombre . '" para el caso #' . ($event->validacionLegal->caso_id ?? 'N/A') . '. El estado actual es "' . $event->validacionLegal->estado . '".',
            'estado' => 'pendiente',
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