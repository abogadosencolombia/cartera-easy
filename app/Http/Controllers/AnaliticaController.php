<?php

namespace App\Http\Controllers;

use App\Models\Caso;
use App\Models\Cooperativa;
use App\Models\IncidenteJuridico;
use App\Models\PagoCaso;
use App\Models\User;
use App\Models\ValidacionLegal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;

class AnaliticaController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $request->validate([
            'cooperativa_id' => 'nullable|exists:cooperativas,id',
            'fecha_desde' => 'nullable|date',
            'fecha_hasta' => 'nullable|date|after_or_equal:fecha_desde',
        ]);

        $user = Auth::user();
        $cooperativaId = $request->input('cooperativa_id');
        $fechaDesde = $request->input('fecha_desde');
        $fechaHasta = $request->input('fecha_hasta');

        // --- Consulta Base de Casos (filtrada por permisos y fechas) ---
        $queryCasos = Caso::query();
        if ($user->tipo_usuario === 'admin') {
            if ($cooperativaId) {
                $queryCasos->where('cooperativa_id', $cooperativaId);
            }
        } else {
            $cooperativasUsuario = $user->cooperativas->pluck('id');
            $queryCasos->whereIn('cooperativa_id', $cooperativasUsuario);
        }
        if ($fechaDesde) $queryCasos->whereDate('created_at', '>=', $fechaDesde);
        if ($fechaHasta) $queryCasos->whereDate('created_at', '<=', $fechaHasta);
        
        // --- Cálculos basados en los filtros ---
        $casosDeCooperativaIds = (clone $queryCasos)->pluck('id');
        $validacionesQuery = ValidacionLegal::whereIn('caso_id', $casosDeCooperativaIds);
        
        $kpisCasos = (clone $queryCasos)->select('estado_proceso', DB::raw('count(*) as total'))->groupBy('estado_proceso')->pluck('total', 'estado_proceso');
        $moraTotal = (clone $queryCasos)->where('estado_proceso', '!=', 'cerrado')->sum('monto_total');
        
        $totalValidaciones = (clone $validacionesQuery)->count();
        $validacionesIncumplidas = (clone $validacionesQuery)->where('estado', 'incumple')->count();
        $porcentajeCumplimiento = $totalValidaciones > 0 ? round((($totalValidaciones - $validacionesIncumplidas) / $totalValidaciones) * 100, 1) : 100;
        $validacionesPorEstado = (clone $validacionesQuery)->select('estado', DB::raw('count(*) as total'))->groupBy('estado')->pluck('total', 'estado');

        // ===== RANKING CORREGIDO: Ahora incluye a 'gestor' =====
        $rankingQuery = User::select('users.name', DB::raw('SUM(pagos_caso.monto_pagado) as total_recuperado'))
            ->join('casos', 'users.id', '=', 'casos.user_id')
            ->join('pagos_caso', 'casos.id', '=', 'pagos_caso.caso_id')
            ->whereIn('users.tipo_usuario', ['abogado', 'gestor']) // <-- ¡AQUÍ LA MEJORA!
            ->when($cooperativaId, fn($q) => $q->where('casos.cooperativa_id', $cooperativaId))
            ->when($fechaDesde, fn($q) => $q->whereDate('pagos_caso.fecha_pago', '>=', $fechaDesde))
            ->when($fechaHasta, fn($q) => $q->whereDate('pagos_caso.fecha_pago', '<=', $fechaHasta))
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total_recuperado')
            ->limit(5); // Aumentamos a Top 5
        
        if ($user->tipo_usuario !== 'admin') {
            $rankingQuery->whereIn('casos.cooperativa_id', $user->cooperativas->pluck('id'));
        }
        $rankingAbogados = $rankingQuery->get();

        // ===== TENDENCIA DE INCIDENTES CORREGIDA: Ahora es global =====
        // Usamos una nueva consulta que no se ve afectada por el filtro de cooperativa
        $fechaGraficoHasta = $fechaHasta ? Carbon::parse($fechaHasta) : now();
        $incidentesPorMes = IncidenteJuridico::query()
            ->select(DB::raw('YEAR(created_at) as year, MONTH(created_at) as month, count(*) as total'))
            ->whereBetween('created_at', [$fechaGraficoHasta->copy()->subYear(), $fechaGraficoHasta])
            ->groupBy('year', 'month')->orderBy('year', 'asc')->orderBy('month', 'asc')
            ->get()->mapWithKeys(fn($item) => [Carbon::createFromDate($item->year, $item->month, 1)->format('M-y') => $item->total]);

        $cooperativasParaFiltro = ($user->tipo_usuario === 'admin')
            ? Cooperativa::all(['id', 'nombre'])
            : $user->cooperativas()->get(['id', 'nombre']);

        return Inertia::render('Dashboard/Index', [
            'kpis' => [
                'casos_activos' => $kpisCasos->except('cerrado')->sum(),
                'casos_demandados' => $kpisCasos->get('demandado', 0),
                'mora_total' => number_format($moraTotal, 0, ',', '.'),
                'cumplimiento_legal' => $porcentajeCumplimiento,
            ],
            'chartData' => [
                'casosPorEstado' => $kpisCasos,
                'incidentesPorMes' => $incidentesPorMes,
                'validacionesPorEstado' => $validacionesPorEstado,
            ],
            'rankingAbogados' => $rankingAbogados,
            'cooperativas' => $cooperativasParaFiltro,
            'filters' => $request->only(['cooperativa_id', 'fecha_desde', 'fecha_hasta']),
        ]);
    }
}
