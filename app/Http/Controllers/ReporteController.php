<?php

namespace App\Http\Controllers;

use App\Events\ReporteExportado;
use App\Models\Caso;
use App\Models\Cooperativa;
use App\Exports\CasosReportExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\PagoCaso;
use App\Models\GestionPagoContrato;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;
use PDF;
use App\Models\NotificacionCaso;
use App\Models\ValidacionLegal;
use App\Exports\CumplimientoReportExport;

class ReporteController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('view-reports');
        $user = auth()->user();
        
        $fuente = $request->input('tipo_fuente', 'todos');

        // --- 1. CONSULTAS BASE ---
        $casosQuery = Caso::query();
        $radicadosQuery = \App\Models\ProcesoRadicado::query();
        
        if ($user->tipo_usuario !== 'admin') {
            $cooperativaIds = $user->cooperativas->pluck('id');
            $casosQuery->whereIn('cooperativa_id', $cooperativaIds);
            
            $radicadosQuery->where(function($q) use ($user, $cooperativaIds) {
                $q->where('abogado_id', $user->id)
                  ->orWhere('responsable_revision_id', $user->id)
                  ->orWhereHas('demandantes.cooperativas', fn($cq) => $cq->whereIn('cooperativas.id', $cooperativaIds))
                  ->orWhereHas('demandados.cooperativas', fn($cq) => $cq->whereIn('cooperativas.id', $cooperativaIds));
            });
        }
        
        // --- 2. FILTROS ---
        $userFilter = $request->input('user_id');
        $coopFilter = $request->input('cooperativa_id');
        $fechaDesde = $request->input('fecha_desde', Carbon::now()->subYear()->format('Y-m-d'));
        $fechaHasta = $request->input('fecha_hasta', Carbon::now()->format('Y-m-d'));

        $casosQuery->when($coopFilter, fn($q) => $q->where('cooperativa_id', $coopFilter));
        $casosQuery->when($userFilter, fn($q) => $q->where('user_id', $userFilter));
        $casosQuery->when($fechaDesde, fn($q) => $q->whereDate('created_at', '>=', $fechaDesde));
        $casosQuery->when($fechaHasta, fn($q) => $q->whereDate('created_at', '<=', $fechaHasta));

        $radicadosQuery->when($userFilter, fn($q) => $q->where('abogado_id', $userFilter));
        $radicadosQuery->when($coopFilter, function($q) use ($coopFilter) {
            $q->where(function($sq) use ($coopFilter) {
                $sq->whereHas('demandantes.cooperativas', fn($cq) => $cq->where('cooperativas.id', $coopFilter))
                  ->orWhereHas('demandados.cooperativas', fn($cq) => $cq->where('cooperativas.id', $coopFilter));
            });
        });
        $radicadosQuery->when($fechaDesde, fn($q) => $q->whereDate('created_at', '>=', $fechaDesde));
        $radicadosQuery->when($fechaHasta, fn($q) => $q->whereDate('created_at', '<=', $fechaHasta));

        $casosIds = ($fuente === 'radicados') ? [] : (clone $casosQuery)->pluck('id');
        $radicadosIds = ($fuente === 'casos') ? [] : (clone $radicadosQuery)->pluck('id');

        // --- 3. KPIs FINANCIEROS ---
        $sumaPagosCasos = DB::table('pagos_caso')->whereIn('caso_id', $casosIds)->whereBetween('fecha_pago', [$fechaDesde, $fechaHasta])->sum('monto_pagado');
        $sumaPagosContratosCasos = DB::table('contrato_pagos')->join('contratos', 'contrato_pagos.contrato_id', '=', 'contratos.id')->whereIn('contratos.caso_id', $casosIds)->whereBetween('contrato_pagos.fecha', [$fechaDesde, $fechaHasta])->sum('contrato_pagos.valor');
        $sumaPagosContratosRadicados = DB::table('contrato_pagos')->join('contratos', 'contrato_pagos.contrato_id', '=', 'contratos.id')->whereIn('contratos.proceso_id', $radicadosIds)->whereBetween('contrato_pagos.fecha', [$fechaDesde, $fechaHasta])->sum('contrato_pagos.valor');

        $totalRecuperado = (float)$sumaPagosCasos + (float)$sumaPagosContratosCasos + (float)$sumaPagosContratosRadicados;
        $carteraEnMora = ($fuente === 'radicados') ? 0 : (clone $casosQuery)->whereNotNull('fecha_vencimiento')->where('fecha_vencimiento', '<', now())->sum('monto_total');

        // --- 4. RANKING ---
        $rankingAbogados = User::whereIn('tipo_usuario', ['abogado', 'gestor'])->withCount(['casos as casos_count' => fn($q) => $q->whereIn('casos.id', $casosIds)])->get();
        $pSumaCasos = DB::table('pagos_caso')->whereIn('caso_id', $casosIds)->whereBetween('fecha_pago', [$fechaDesde, $fechaHasta])->select('user_id', DB::raw('SUM(monto_pagado) as total'))->groupBy('user_id')->pluck('total', 'user_id');
        $pSumaCCasos = DB::table('contrato_pagos')
            ->join('contratos', 'contrato_pagos.contrato_id', '=', 'contratos.id')
            ->join('casos', 'contratos.caso_id', '=', 'casos.id')
            ->whereIn('casos.id', $casosIds)
            ->whereBetween('contrato_pagos.fecha', [$fechaDesde, $fechaHasta])
            ->select('casos.user_id', DB::raw('SUM(contrato_pagos.valor) as total'))
            ->groupBy('casos.user_id')
            ->pluck('total', 'casos.user_id');
        $pSumaCRadicados = DB::table('contrato_pagos')->join('contratos', 'contrato_pagos.contrato_id', '=', 'contratos.id')->join('proceso_radicados', 'contratos.proceso_id', '=', 'proceso_radicados.id')->whereIn('proceso_radicados.id', $radicadosIds)->whereBetween('contrato_pagos.fecha', [$fechaDesde, $fechaHasta])->select('proceso_radicados.abogado_id', DB::raw('SUM(contrato_pagos.valor) as total'))->groupBy('proceso_radicados.abogado_id')->pluck('total', 'proceso_radicados.abogado_id');

        $rankingAbogados->each(function ($u) use ($pSumaCasos, $pSumaCCasos, $pSumaCRadicados) {
            $u->pagos_sum_monto_pagado = (float)$pSumaCasos->get($u->id, 0) + (float)$pSumaCCasos->get($u->id, 0) + (float)$pSumaCRadicados->get($u->id, 0);
        });
        $rankingAbogados = $rankingAbogados->sortByDesc('pagos_sum_monto_pagado')->take(10)->values();

        // --- 5. GRÁFICA ---
        $pagosMensuales = collect([]);
        if ($fuente !== 'radicados') {
            $pMCasos = DB::table('pagos_caso')->whereIn('caso_id', $casosIds)->where('fecha_pago', '>=', Carbon::now()->subYear())->select(DB::raw('EXTRACT(YEAR FROM fecha_pago) as anio, EXTRACT(MONTH FROM fecha_pago) as mes, SUM(monto_pagado) as total'))->groupBy('anio', 'mes')->get();
            $pagosMensuales = $pagosMensuales->concat($pMCasos);
        }
        $pMContratos = DB::table('contrato_pagos')->join('contratos', 'contrato_pagos.contrato_id', '=', 'contratos.id')->where(fn($q) => $q->whereIn('contratos.caso_id', $casosIds)->orWhereIn('contratos.proceso_id', $radicadosIds))->where('contrato_pagos.fecha', '>=', Carbon::now()->subYear())->select(DB::raw('EXTRACT(YEAR FROM contrato_pagos.fecha) as anio, EXTRACT(MONTH FROM contrato_pagos.fecha) as mes, SUM(contrato_pagos.valor) as total'))->groupBy('anio', 'mes')->get();
        $pagosMensuales = $pagosMensuales->concat($pMContratos)->groupBy(fn($i) => $i->anio.'-'.$i->mes)->map(fn($g) => (object)['anio' => $g->first()->anio, 'mes' => $g->first()->mes, 'total' => $g->sum('total')])->sortBy(fn($i) => $i->anio * 100 + $i->mes)->values();

        // --- 6. CUMPLIMIENTO LEGAL ---
        $baseValidacionesQuery = ValidacionLegal::whereIn('caso_id', $casosIds);
        $totalFallasActivas = (clone $baseValidacionesQuery)->where('estado', 'incumple')->count();
        $fallasPorRiesgo = (clone $baseValidacionesQuery)->where('estado', 'incumple')
            ->select('nivel_riesgo', DB::raw('count(*) as total'))
            ->groupBy('nivel_riesgo')->pluck('total', 'nivel_riesgo');
            
        $fallasPorCooperativa = (clone $baseValidacionesQuery)
            ->where('validaciones_legales.estado', 'incumple')
            ->join('casos', 'validaciones_legales.caso_id', '=', 'casos.id')
            ->join('cooperativas', 'casos.cooperativa_id', '=', 'cooperativas.id')
            ->select('cooperativas.nombre', DB::raw('count(*) as total_fallas'))
            ->groupBy('cooperativas.nombre')
            ->orderByDesc('total_fallas')->limit(5)->get();

        $alertasPorTipo = NotificacionCaso::deExpedientesEnSeguimiento()
            ->where(function($q) use ($casosIds, $radicadosIds) {
                $q->whereIn('caso_id', $casosIds)
                  ->orWhereIn('proceso_id', $radicadosIds);
            })
            ->whereNull('atendida_en')
            ->select('tipo', DB::raw('count(*) as total'))
            ->groupBy('tipo')
            ->get();

        $listadoFallas = (clone $baseValidacionesQuery)
            ->where('estado', 'incumple')
            ->with(['caso.deudor' => fn($q) => $q->withTrashed(), 'caso.cooperativa'])
            ->orderByRaw("CASE WHEN nivel_riesgo = 'alto' THEN 1 WHEN nivel_riesgo = 'medio' THEN 2 WHEN nivel_riesgo = 'bajo' THEN 3 ELSE 4 END")
            ->latest('ultima_revision')->limit(20)->get();

        return Inertia::render('Reportes/Dashboard', [
            'kpis' => [
                'casos_activos' => (float)(clone $casosQuery)->count() + (float)(clone $radicadosQuery)->count(), 
                'total_recuperado' => $totalRecuperado, 
                'cartera_en_mora' => (float)$carteraEnMora,
                'alertas_vencidas' => NotificacionCaso::deExpedientesEnSeguimiento()->where(function($q) use ($casosIds, $radicadosIds) {
                        $q->whereIn('caso_id', $casosIds)->orWhereIn('proceso_id', $radicadosIds);
                    })->whereNull('atendida_en')->where('created_at', '<', now()->subDays(7))->count(),
                'total_alertas_activas' => NotificacionCaso::deExpedientesEnSeguimiento()->where(function($q) use ($casosIds, $radicadosIds) {
                        $q->whereIn('caso_id', $casosIds)->orWhereIn('proceso_id', $radicadosIds);
                    })->whereNull('atendida_en')->count(),
                'casos_sin_pagare' => ($fuente === 'radicados') ? 0 : (clone $casosQuery)->whereDoesntHave('documentos', fn ($q) => $q->where('tipo_documento', 'ilike', 'paga%'))->count(),
            ],
            'graficas' => [ 
                'pagos_mensuales' => $pagosMensuales, 
                'alertas_por_tipo' => $alertasPorTipo, 
            ],
            'rankingAbogados' => $rankingAbogados, 
            'cooperativas' => ($user->tipo_usuario === 'admin') ? Cooperativa::all(['id', 'nombre']) : $user->cooperativas,
            'abogadosYGestores' => User::whereIn('tipo_usuario', ['abogado', 'gestor'])->get(['id', 'name']),
            'filtros' => $request->only(['cooperativa_id', 'user_id', 'tipo_proceso', 'fecha_desde', 'fecha_hasta', 'tipo_fuente']),
            'statsCumplimiento' => [ 
                'totalFallasActivas' => $totalFallasActivas, 
                'fallasPorRiesgo' => [
                    'alto' => $fallasPorRiesgo->get('alto', 0),
                    'medio' => $fallasPorRiesgo->get('medio', 0),
                    'bajo' => $fallasPorRiesgo->get('bajo', 0),
                ], 
                'fallasPorCooperativa' => $fallasPorCooperativa 
            ],
            'listadoFallas' => $listadoFallas,
        ]);
    }

    public function exportar(Request $request)
    {
        Gate::authorize('view-reports');
        $user = auth()->user();
        $casosQuery = Caso::with(['cooperativa', 'deudor' => fn($q) => $q->withTrashed(), 'user']);
        $filtros = $request->only(['cooperativa_id', 'user_id', 'tipo_proceso', 'fecha_desde', 'fecha_hasta']);
        if ($user->tipo_usuario !== 'admin') {
            $cooperativaIds = $user->cooperativas->pluck('id');
            $casosQuery->whereIn('cooperativa_id', $cooperativaIds);
        }
        $casosQuery->when($request->filled('cooperativa_id'), fn($q) => $q->where('cooperativa_id', $request->input('cooperativa_id')));
        $casosQuery->when($request->filled('user_id'), fn($q) => $q->where('user_id', $request->input('user_id')));
        $casosQuery->when($request->filled('tipo_proceso'), fn($q) => $q->where('tipo_proceso', 'ilike', "%{$request->input('tipo_proceso')}%"));
        $casosQuery->when($request->filled('fecha_desde'), fn($q) => $q->whereDate('created_at', '>=', $request->input('fecha_desde')));
        $casosQuery->when($request->filled('fecha_hasta'), fn($q) => $q->whereDate('created_at', '<=', $request->input('fecha_hasta')));
        $tipo = $request->input('tipo', 'xlsx');
        $fileName = 'reporte_casos_' . now()->format('Y-m-d_H-i-s');
        event(new ReporteExportado($user, $tipo, $filtros));
        switch ($tipo) {
            case 'csv': return Excel::download(new CasosReportExport($casosQuery), $fileName . '.csv', \Maatwebsite\Excel\Excel::CSV);
            case 'pdf': $casos = $casosQuery->get(); $pdf = PDF::loadView('pdf.reporte_casos', ['casos' => $casos]); return $pdf->setPaper('a4', 'landscape')->download($fileName . '.pdf');
            default: return Excel::download(new CasosReportExport($casosQuery), $fileName . '.xlsx');
        }
    }
    
    public function exportarCumplimiento(Request $request)
    {
        Gate::authorize('view-reports');
        $user = auth()->user();
        $casosQuery = Caso::query();
        if ($user->tipo_usuario !== 'admin') { $cooperativaIds = $user->cooperativas->pluck('id'); $casosQuery->whereIn('cooperativa_id', $cooperativaIds); }
        $casosQuery->when($request->filled('cooperativa_id'), fn($q) => $q->where('cooperativa_id', $request->input('cooperativa_id')));
        $casosQuery->when($request->filled('user_id'), fn($q) => $q->where('user_id', $request->input('user_id')));
        $casosQuery->when($request->filled('tipo_proceso'), fn($q) => $q->where('tipo_proceso', $request->input('tipo_proceso')));
        $casosQuery->when($request->filled('fecha_desde'), fn($q) => $q->whereDate('created_at', '>=', $request->input('fecha_desde')));
        $casosQuery->when($request->filled('fecha_hasta'), fn($q) => $q->whereDate('created_at', '<=', $request->input('fecha_hasta')));
        $fallasQuery = ValidacionLegal::where('estado', 'incumple')->whereIn('caso_id', (clone $casosQuery)->pluck('id'))->with(['caso.deudor', 'caso.cooperativa'])->orderByRaw("CASE WHEN nivel_riesgo = 'alto' THEN 1 WHEN nivel_riesgo = 'medio' THEN 2 WHEN nivel_riesgo = 'bajo' THEN 3 ELSE 4 END")->latest('ultima_revision');
        return Excel::download(new CumplimientoReportExport($fallasQuery), 'reporte_cumplimiento_' . now()->format('Y-m-d') . '.xlsx');
    }
}
