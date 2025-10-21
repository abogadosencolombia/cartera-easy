<?php

namespace App\Http\Controllers\Juridico;

use App\Http\Controllers\Controller;
use App\Models\DecisionComiteEtica;
use App\Models\IncidenteJuridico;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class IndicadoresController extends Controller
{
    /**
     * Muestra el panel de indicadores jurídicos.
     */
    public function __invoke(Request $request): Response
    {
        // --- 1. Indicadores Clave (KPIs) ---
        $incidentesActivos = IncidenteJuridico::whereIn('estado', ['pendiente', 'en_revision'])->count();
        $incidentesEnRevision = IncidenteJuridico::where('estado', 'en_revision')->count();
        $sancionesEmitidas = DecisionComiteEtica::where('resultado', 'sancionado')->count();

        // --- 2. Cálculo del Tiempo Promedio de Resolución (en días) ---
        $tiempoPromedio = DecisionComiteEtica::join('tickets_disciplinarios', 'decisiones_comite_etica.ticket_id', '=', 'tickets_disciplinarios.id')
            ->join('incidentes_juridicos', 'tickets_disciplinarios.incidente_id', '=', 'incidentes_juridicos.id')
            ->select(DB::raw('AVG(decisiones_comite_etica.fecha_revision - incidentes_juridicos.fecha_registro) as dias'))
            ->value('dias');

        // --- 3. Datos para Gráfico de Incidentes por Estado ---
        $incidentesPorEstado = IncidenteJuridico::select('estado', DB::raw('count(*) as total'))
            ->groupBy('estado')
            ->pluck('total', 'estado');

        // --- 4. Datos para Gráfico de Decisiones por Resultado ---
        $decisionesPorResultado = DecisionComiteEtica::select('resultado', DB::raw('count(*) as total'))
            ->groupBy('resultado')
            ->pluck('total', 'resultado');

        // ===== RUTA CORREGIDA A LA VISTA =====
        return Inertia::render('Indicadores/Index', [
            'kpis' => [
                'activos' => $incidentesActivos,
                'en_revision' => $incidentesEnRevision,
                'sanciones' => $sancionesEmitidas,
                'tiempo_promedio' => round($tiempoPromedio, 1) ?? 0,
            ],
            'chartData' => [
                'incidentesPorEstado' => $incidentesPorEstado,
                'decisionesPorResultado' => $decisionesPorResultado,
            ]
        ]);
    }
}
