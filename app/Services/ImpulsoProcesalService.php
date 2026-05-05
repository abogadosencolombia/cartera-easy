<?php

namespace App\Services;

use App\Models\Caso;
use App\Models\ProcesoRadicado;
use Carbon\Carbon;

class ImpulsoProcesalService
{
    /**
     * Analiza el despacho global y genera una lista de "Acciones de Impacto"
     */
    public function obtenerRadarDeAccion($limit = 10)
    {
        $acciones = collect();
        $hoy = Carbon::now();

        // 1. ANALIZAR RADICADOS (ABOGADOS COLOMBIA)
        $radicados = ProcesoRadicado::paraSeguimiento()
            ->with(['etapaActual'])
            ->orderBy('updated_at', 'asc') // Los más "olvidados" primero
            ->take($limit * 2)
            ->get();

        foreach ($radicados as $r) {
            $diasInactivo = (int) $r->updated_at->diffInDays($hoy);
            $etapa = $r->etapaActual?->nombre ?? 'Sin Etapa';
            
            $sugerencia = $this->calcularSugerenciaRadicado($r, $diasInactivo, $etapa);
            
            if ($sugerencia) {
                $acciones->push([
                    'id' => $r->id,
                    'tipo' => 'RADICADO',
                    'identificador' => $r->radicado ?? "ID #{$r->id}",
                    'prioridad' => $diasInactivo > 15 ? 'CRÍTICA' : 'ALTA',
                    'dias_inactivo' => $diasInactivo,
                    'etapa_actual' => $etapa,
                    'accion_sugerida' => $sugerencia['texto'],
                    'color' => $sugerencia['color'],
                    'url' => route('procesos.show', $r->id)
                ]);
            }
        }

        // 2. ANALIZAR CASOS (COOPERATIVAS)
        $casos = Caso::paraSeguimiento()
            ->orderBy('updated_at', 'asc')
            ->take($limit * 2)
            ->get();

        foreach ($casos as $c) {
            $diasInactivo = (int) $c->updated_at->diffInDays($hoy);
            
            if ($diasInactivo >= 20) {
                $acciones->push([
                    'id' => $c->id,
                    'tipo' => 'CASO',
                    'identificador' => $c->radicado ?? "CASO #{$c->id}",
                    'prioridad' => $diasInactivo > 20 ? 'CRÍTICA' : 'ALTA',
                    'dias_inactivo' => $diasInactivo,
                    'etapa_actual' => $c->estado_proceso,
                    'accion_sugerida' => 'Actualizar bitácora de gestión de cobro e investigar bienes.',
                    'color' => 'blue',
                    'url' => route('casos.show', $c->id)
                ]);
            }
        }

        return $acciones->sortByDesc(function($a) {
            return ($a['prioridad'] === 'CRÍTICA' ? 100 : 0) + $a['dias_inactivo'];
        })->take($limit)->values();
    }

    private function calcularSugerenciaRadicado($r, $dias, $etapa)
    {
        $etapaLower = strtolower($etapa);

        if (str_contains($etapaLower, 'demanda') || str_contains($etapaLower, 'radica')) {
            if ($dias > 5) return ['texto' => 'Solicitar estado de admisión / Auto admisor.', 'color' => 'indigo'];
        }

        if (str_contains($etapaLower, 'notifica')) {
            return ['texto' => 'Gestionar oficios de notificación o solicitar emplazamiento.', 'color' => 'amber'];
        }

        if (str_contains($etapaLower, 'medida') || str_contains($etapaLower, 'cautelar')) {
            return ['texto' => 'Verificar respuesta de entidades (Bancos/Oficinas de Registro).', 'color' => 'red'];
        }

        if (str_contains($etapaLower, 'sentencia') || str_contains($etapaLower, 'fallo')) {
            return ['texto' => 'Solicitar liquidación de crédito y costas.', 'color' => 'emerald'];
        }

        if ($dias > 15) {
            return ['texto' => 'Radicar memorial de impulso procesal genérico.', 'color' => 'rose'];
        }

        return null;
    }
}
