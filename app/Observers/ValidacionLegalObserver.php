<?php

namespace App\Observers;

use App\Models\BitacoraCaso;
use App\Models\HistorialValidacionLegal;
use App\Models\ValidacionLegal;
use Illuminate\Support\Facades\Auth;
use App\Events\ValidacionLegalIncumplida; // <-- 1. IMPORTAMOS NUESTRO EVENTO

class ValidacionLegalObserver
{
    /**
     * Handle the ValidacionLegal "created" event.
     */
    public function created(ValidacionLegal $validacionLegal): void
    {
        HistorialValidacionLegal::create([
            'validacion_legal_id' => $validacionLegal->id,
            'estado_anterior' => null,
            'estado_nuevo' => $validacionLegal->estado,
            'user_id' => Auth::id(),
            'comentario' => 'Registro inicial de la validación.',
        ]);
        
        if ($validacionLegal->estado === 'incumple') {
            $this->registrarEnBitacora($validacionLegal, 'Falla de Cumplimiento Detectada');
            
            // ===== AUTOMATIZACIÓN MÓDULO 9 (PUNTO 1) =====
            // Si la validación NACE con estado 'incumple', disparamos el evento.
            if ($validacionLegal->caso && $validacionLegal->caso->user) {
                ValidacionLegalIncumplida::dispatch($validacionLegal, $validacionLegal->caso->user);
            }
            // ===============================================
        }
    }

    /**
     * Handle the ValidacionLegal "updated" event.
     */
    public function updated(ValidacionLegal $validacionLegal): void
    {
        if ($validacionLegal->isDirty('estado')) {
            $estadoAnterior = $validacionLegal->getOriginal('estado');
            $estadoNuevo = $validacionLegal->estado;

            HistorialValidacionLegal::create([
                'validacion_legal_id' => $validacionLegal->id,
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => $estadoNuevo,
                'user_id' => Auth::id(),
                'comentario' => 'Cambio de estado de la validación.',
            ]);
            
            if ($estadoNuevo === 'incumple') {
                $this->registrarEnBitacora($validacionLegal, '🔴 Falla de Cumplimiento Detectada');

                // ===== AUTOMATIZACIÓN MÓDULO 9 (PUNTO 2) =====
                // Si la validación CAMBIA a estado 'incumple', disparamos el evento.
                if ($validacionLegal->caso && $validacionLegal->caso->user) {
                    ValidacionLegalIncumplida::dispatch($validacionLegal, $validacionLegal->caso->user);
                }
                // ===============================================

            } 
            elseif ($estadoAnterior === 'incumple' && $estadoNuevo === 'cumple') {
                 $this->registrarEnBitacora($validacionLegal, '🟢 Falla de Cumplimiento Corregida');
            }
        }
    }

    private function registrarEnBitacora(ValidacionLegal $validacion, string $accion): void
    {
        $validacion->caso->bitacoras()->create([
            'user_id' => Auth::id(),
            'accion' => $accion,
            'comentario' => "Validación: '" . (ValidacionLegal::TIPOS_VALIDACION[$validacion->tipo] ?? $validacion->tipo) . "'. Nuevo estado: {$validacion->estado}. Riesgo: {$validacion->nivel_riesgo}."
        ]);
    }
}