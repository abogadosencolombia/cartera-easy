<?php

namespace App\Observers;

use App\Models\AuditoriaEvento;
use App\Models\IncidenteJuridico;
use Illuminate\Support\Facades\Log;

class AuditoriaObserver
{
    /**
     * Se dispara cuando se crea un evento de auditoría.
     */
    public function created(AuditoriaEvento $evento): void
    {
        // Solo elevamos a incidente si la criticidad es alta o extrema.
        if (in_array($evento->criticidad, ['alta', 'extrema'])) {
            try {
                IncidenteJuridico::create([
                    'usuario_responsable_id' => $evento->user_id,
                    'origen' => 'auditoria',
                    'asunto' => '🚨 Alerta de Seguridad: ' . $evento->evento,
                    'descripcion' => "Se ha registrado un evento de auditoría con criticidad {$evento->criticidad}.\n\nDetalle: {$evento->descripcion_breve}\nIP: {$evento->direccion_ip}\nNavegador: {$evento->user_agent}",
                    'estado' => 'pendiente',
                    'fecha_registro' => now(),
                ]);
            } catch (\Exception $e) {
                Log::error('Error al crear incidente automático desde auditoría: ' . $e->getMessage());
            }
        }
    }
}