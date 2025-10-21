<?php

namespace App\Listeners;

use App\Events\FallaDeCumplimientoCorregida;
use App\Models\AuditoriaEvento;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class LogFallaCorregida
{
    /**
     * Handle the event.
     */
    public function handle(FallaDeCumplimientoCorregida $event): void
    {
        $nombreValidacion = \App\Models\ValidacionLegal::TIPOS_VALIDACION[$event->validacion->tipo] ?? $event->validacion->tipo;

        AuditoriaEvento::create([
            'user_id' => $event->user->id,
            'evento' => 'CUMPLIMIENTO_CORREGIDO',
            'descripcion_breve' => "Usuario '{$event->user->name}' corrigió la falla '{$nombreValidacion}' para el Caso #{$event->validacion->caso_id}.",
            'auditable_id' => $event->validacion->id,
            'auditable_type' => \App\Models\ValidacionLegal::class,
            'criticidad' => 'media',
            'detalle_nuevo' => $event->validacion->toJson(), // Guardamos el estado final de la validación
            'direccion_ip' => $event->request->ip(),
            'user_agent' => $event->request->userAgent(),
        ]);
    }
}
