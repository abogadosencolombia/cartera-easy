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

        // --- CONSTRUCCIÓN DE CONSULTAS BASE ---
        $casosQuery = Caso::query();
        
        if ($user->tipo_usuario !== 'admin') {
            $cooperativaIds = $user->cooperativas->pluck('id');
            $casosQuery->whereIn('cooperativa_id', $cooperativaIds);
        }
        
        // --- APLICACIÓN DE FILTROS DINÁMICOS ---
        $casosQuery->when($request->filled('cooperativa_id'), fn($q) => $q->where('cooperativa_id', $request->input('cooperativa_id')));
        $casosQuery->when($request->filled('user_id'), fn($q) => $q->where('user_id', $request->input('user_id')));
        $casosQuery->when($request->filled('tipo_proceso'), fn($q) => $q->where('tipo_proceso', 'ilike', "%{$request->input('tipo_proceso')}%"));
        // ELIMINADO: Filtro estado_proceso
        $casosQuery->when($request->filled('fecha_desde'), fn($q) => $q->whereDate('created_at', '>=', $request->input('fecha_desde')));
        $casosQuery->when($request->filled('fecha_hasta'), fn($q) => $q->whereDate('created_at', '<=', $request->input('fecha_hasta')));

        $casosIds = (clone $casosQuery)->pluck('id');

        // --- DEFINIR CONSULTAS DE PAGO ---
        $pagosCasoQuery = PagoCaso::query()->whereIn('caso_id', $casosIds);

        $pagosContratoQuery = GestionPagoContrato::query()->whereHas('contrato', function ($q) use ($casosIds) {
            $q->whereIn('caso_id', $casosIds);
        });

        // --- CÁLCULO DE KPIs ESTRATÉGICOS ---
        
        $totalCasosActivos = (clone $casosQuery)->count(); 
        
        $carteraEnMora = (clone $casosQuery)
            ->whereNotNull('fecha_vencimiento')
            ->where('fecha_vencimiento', '<', now())
            ->sum('monto_total');
        
        $totalRecuperado = (clone $pagosCasoQuery)->sum('monto_pagado') + (clone $pagosContratoQuery)->sum('valor');
        
        $promedioDiasRecuperacion = 0;

        $casosActivosQuery = (clone $casosQuery); 
        $casosSinPagare = (clone $casosActivosQuery)->whereDoesntHave('documentos', fn ($q) => $q->where('tipo_documento', 'ilike', 'pagaré'))->count();
        $porcentajeSinPagare = ($totalCasosActivos > 0) ? ($casosSinPagare / $totalCasosActivos) * 100 : 0;
        $casosInactivos = (clone $casosActivosQuery)->where('updated_at', '<', Carbon::now()->subDays(60))->count();
        
        $casosConDocumentosFaltantes = (clone $casosActivosQuery)
            ->whereHas('validacionesLegales', function ($query) {
                $query->where('tipo', 'documento_requerido')->where('estado', 'incumple');
            })->count();
            
        $casosLongevos = (clone $casosActivosQuery)->where('fecha_apertura', '<', Carbon::now()->subYear())->count();
        $notificacionesQuery = NotificacionCaso::whereIn('caso_id', $casosIds);
        $alertasPorTipo = (clone $notificacionesQuery)->whereNull('atendida_en')->groupBy('tipo')->select('tipo', DB::raw('count(*) as total'))->pluck('total', 'tipo');
        $alertasVencidas = (clone $notificacionesQuery)->whereNull('atendida_en')->where('created_at', '<', now()->subDays(7))->count();
        $totalAlertasActivas = (clone $notificacionesQuery)->whereNull('atendida_en')->count();
        $tendenciaSemanalAlertas = (clone $notificacionesQuery)->where('created_at', '>=', now()->subDays(7))->groupBy('date')->orderBy('date', 'ASC')->get([ DB::raw('DATE(created_at) as date'), DB::raw('count(*) as total') ]);
        
        $consolidadoCooperativa = null;
        if ($request->filled('cooperativa_id')) {
            $coopId = $request->input('cooperativa_id');
            $cooperativaSeleccionada = Cooperativa::find($coopId);
            if ($cooperativaSeleccionada) {
                $consolidadoCooperativa = [ 
                    'nombre' => $cooperativaSeleccionada->nombre, 
                    'casos_activos' => (clone $casosQuery)->count(), 
                    'cartera_en_mora' => (clone $casosQuery)->whereNotNull('fecha_vencimiento')->where('fecha_vencimiento', '<', now())->sum('monto_total'), 
                    'total_recuperado' => $totalRecuperado, 
                ];
            }
        }

        // --- UNIÓN DE PAGOS MENSUALES ---
        $pagosCasoMensuales = (clone $pagosCasoQuery)
            ->select(DB::raw('EXTRACT(YEAR FROM fecha_pago) as anio, EXTRACT(MONTH FROM fecha_pago) as mes, SUM(monto_pagado) as total'))
            ->where('fecha_pago', '>=', Carbon::now()->subYear())
            ->groupBy('anio', 'mes');

        $pagosContratoUnidos = (clone $pagosContratoQuery)
            ->select(DB::raw('EXTRACT(YEAR FROM fecha) as anio, EXTRACT(MONTH FROM fecha) as mes, SUM(valor) as total'))
            ->where('fecha', '>=', Carbon::now()->subYear())
            ->groupBy('anio', 'mes')
            ->union($pagosCasoMensuales)
            ->get();
            
        $pagosMensuales = $pagosContratoUnidos->groupBy(function($item) {
            return $item->anio . '-' . $item->mes;
        })->map(function($group) {
            return (object) [
                'anio' => $group->first()->anio,
                'mes' => $group->first()->mes,
                'total' => $group->sum('total'),
            ];
        })->sortBy(function($item) {
            return $item->anio * 100 + $item->mes;
        })->values();


        // --- GRÁFICAS ---
        $carteraPorEdad = (clone $casosQuery)
            ->whereNotNull('fecha_vencimiento')
            ->where('fecha_vencimiento', '<', now())
            ->select(DB::raw("CASE 
                WHEN (CURRENT_DATE - fecha_vencimiento::date) BETWEEN 0 AND 30 THEN '1-30 días' 
                WHEN (CURRENT_DATE - fecha_vencimiento::date) BETWEEN 31 AND 60 THEN '31-60 días' 
                WHEN (CURRENT_DATE - fecha_vencimiento::date) BETWEEN 61 AND 90 THEN '61-90 días' 
                WHEN (CURRENT_DATE - fecha_vencimiento::date) BETWEEN 91 AND 120 THEN '91-120 días' 
                ELSE '>120 días' 
            END as rango_mora, SUM(monto_total) as total"))
            ->groupBy('rango_mora')
            ->get()
            ->pluck('total', 'rango_mora');
        
        $vencimientosMensuales = (clone $casosQuery)
            ->whereNotNull('fecha_vencimiento')
            ->select(DB::raw('EXTRACT(YEAR FROM fecha_vencimiento) as anio, EXTRACT(MONTH FROM fecha_vencimiento) as mes, SUM(monto_total) as total'))
            ->where('fecha_vencimiento', '>=', Carbon::now()->subYear())
            ->groupBy('anio', 'mes')
            ->orderBy('anio', 'asc')
            ->orderBy('mes', 'asc')
            ->get();
        
        // --- RANKING DE ABOGADOS ---
        $fechaDesde = $request->input('fecha_desde', Carbon::now()->subYear()->format('Y-m-d'));
        $fechaHasta = $request->input('fecha_hasta', Carbon::now()->format('Y-m-d'));

        // 1. Obtener base de abogados y gestores
        $rankingAbogados = User::whereIn('tipo_usuario', ['abogado', 'gestor'])
            ->withCount(['casos as casos_count' => fn($q) => $q->whereIn('casos.id', $casosIds)])
            ->get();

        // 2. Sumar pagos registrados en Casos (Atribuidos al usuario que registró el pago si existe, o al dueño del caso)
        $pagosSumaCasos = DB::table('pagos_caso')
            ->whereIn('caso_id', $casosIds)
            ->whereBetween('fecha_pago', [$fechaDesde, $fechaHasta])
            ->select('user_id', DB::raw('SUM(monto_pagado) as total'))
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        // 3. Sumar pagos registrados en Contratos (Atribuidos al dueño del caso vinculado al contrato)
        $pagosSumaContratos = DB::table('contrato_pagos')
            ->join('contratos', 'contrato_pagos.contrato_id', '=', 'contratos.id')
            ->join('casos', 'contratos.caso_id', '=', 'casos.id')
            ->whereIn('casos.id', $casosIds)
            ->whereBetween('contrato_pagos.fecha', [$fechaDesde, $fechaHasta])
            ->select('casos.user_id', DB::raw('SUM(contrato_pagos.valor) as total'))
            ->groupBy('casos.user_id')
            ->pluck('total', 'casos.user_id');

        // 4. Integrar resultados en la colección de abogados
        $rankingAbogados->each(function ($user) use ($pagosSumaCasos, $pagosSumaContratos) {
            $totalCasos = (float) $pagosSumaCasos->get($user->id, 0);
            $totalContratos = (float) $pagosSumaContratos->get($user->id, 0);
            $user->pagos_sum_monto_pagado = $totalCasos + $totalContratos;
        });

        // 5. Ordenar y limitar
        $rankingAbogados = $rankingAbogados->sortByDesc('pagos_sum_monto_pagado')
            ->take(10)
            ->values();

        // --- DATOS COMPLEMENTARIOS ---
        $cooperativas = ($user->tipo_usuario === 'admin') ? Cooperativa::all(['id', 'nombre']) : $user->cooperativas;
        $abogadosYGestores = User::whereIn('tipo_usuario', ['abogado', 'gestor'])->get(['id', 'name']);
        
        $baseValidacionesQuery = ValidacionLegal::whereIn('caso_id', $casosIds);
        $totalFallasActivas = (clone $baseValidacionesQuery)->where('estado', 'incumple')->count();
        $fallasPorRiesgo = (clone $baseValidacionesQuery)->where('estado', 'incumple')->select('nivel_riesgo', DB::raw('count(*) as total'))->groupBy('nivel_riesgo')->pluck('total', 'nivel_riesgo');
        $fallasPorCooperativa = (clone $baseValidacionesQuery)->where('validaciones_legales.estado', 'incumple')->join('casos', 'validaciones_legales.caso_id', '=', 'casos.id')->join('cooperativas', 'casos.cooperativa_id', '=', 'cooperativas.id')->select('cooperativas.nombre', DB::raw('count(*) as total_fallas'))->groupBy('cooperativas.nombre')->orderByDesc('total_fallas')->limit(5)->get();
        $listadoFallas = (clone $baseValidacionesQuery)->where('estado', 'incumple')->with(['caso.deudor' => fn($q) => $q->withTrashed(), 'caso.cooperativa'])->orderByRaw("CASE WHEN nivel_riesgo = 'alto' THEN 1 WHEN nivel_riesgo = 'medio' THEN 2 WHEN nivel_riesgo = 'bajo' THEN 3 ELSE 4 END")->latest('ultima_revision')->get();

        return Inertia::render('Reportes/Dashboard', [
            'kpis' => [
                'casos_activos' => $totalCasosActivos, 
                'total_recuperado' => (float) $totalRecuperado, 
                'cartera_en_mora' => (float) $carteraEnMora, 
                'promedio_dias_recuperacion' => ($promedioDiasRecuperacion !== null) ? round($promedioDiasRecuperacion) : 0,
                'casos_sin_pagare' => $casosSinPagare, 
                'porcentaje_sin_pagare' => round($porcentajeSinPagare, 2), 
                'casos_inactivos' => $casosInactivos, 
                'casos_con_documentos_faltantes' => $casosConDocumentosFaltantes, 
                'casos_longevos' => $casosLongevos, 
                'total_alertas_activas' => $totalAlertasActivas, 
                'alertas_vencidas' => $alertasVencidas,
            ],
            'graficas' => [
                'pagos_mensuales' => $pagosMensuales, 
                // ELIMINADO: casos_por_estado
                'cartera_por_edad' => $carteraPorEdad, 
                'vencimientos_mensuales' => $vencimientosMensuales, 
                'alertas_por_tipo' => $alertasPorTipo, 
                'tendencia_semanal_alertas' => $tendenciaSemanalAlertas,
            ],
            'rankingAbogados' => $rankingAbogados, 
            'consolidadoCooperativa' => $consolidadoCooperativa, 
            'cooperativas' => $cooperativas,
            'abogadosYGestores' => $abogadosYGestores,
            // ELIMINADO: estado_proceso de los filtros devueltos
            'filtros' => $request->only(['cooperativa_id', 'user_id', 'tipo_proceso', 'fecha_desde', 'fecha_hasta']),
            
            'statsCumplimiento' => [
                'totalFallasActivas' => $totalFallasActivas,
                'fallasPorRiesgo' => [ 
                    'alto' => $fallasPorRiesgo->get('alto', 0), 
                    'medio' => $fallasPorRiesgo->get('medio', 0), 
                    'bajo' => $fallasPorRiesgo->get('bajo', 0), 
                ],
                'fallasPorCooperativa' => $fallasPorCooperativa,
            ],
            'listadoFallas' => $listadoFallas,
        ]);
    }

    public function exportar(Request $request)
    {
        Gate::authorize('view-reports');
        $user = auth()->user();

        $casosQuery = Caso::with(['cooperativa', 'deudor' => fn($q) => $q->withTrashed(), 'user']);

        // ELIMINADO: estado_proceso
        $filtros = $request->only(['cooperativa_id', 'user_id', 'tipo_proceso', 'fecha_desde', 'fecha_hasta']);

        if ($user->tipo_usuario !== 'admin') {
            $cooperativaIds = $user->cooperativas->pluck('id');
            $casosQuery->whereIn('cooperativa_id', $cooperativaIds);
        }

        $casosQuery->when($request->filled('cooperativa_id'), fn($q) => $q->where('cooperativa_id', $request->input('cooperativa_id')));
        $casosQuery->when($request->filled('user_id'), fn($q) => $q->where('user_id', $request->input('user_id')));
        $casosQuery->when($request->filled('tipo_proceso'), fn($q) => $q->where('tipo_proceso', 'ilike', "%{$request->input('tipo_proceso')}%"));
        // ELIMINADO: Filtro estado_proceso
        $casosQuery->when($request->filled('fecha_desde'), fn($q) => $q->whereDate('created_at', '>=', $request->input('fecha_desde')));
        $casosQuery->when($request->filled('fecha_hasta'), fn($q) => $q->whereDate('created_at', '<=', $request->input('fecha_hasta')));

        $tipo = $request->input('tipo', 'xlsx');
        $fileName = 'reporte_casos_' . now()->format('Y-m-d_H-i-s');

        event(new ReporteExportado($user, $tipo, $filtros));

        switch ($tipo) {
            case 'csv':
                return Excel::download(new CasosReportExport($casosQuery), $fileName . '.csv', \Maatwebsite\Excel\Excel::CSV);
            case 'pdf':
                $casos = $casosQuery->get();
                $pdf = PDF::loadView('pdf.reporte_casos', ['casos' => $casos]);
                return $pdf->setPaper('a4', 'landscape')->download($fileName . '.pdf');
            case 'xlsx':
            default:
                return Excel::download(new CasosReportExport($casosQuery), $fileName . '.xlsx');
        }
    }
    
    public function exportarCumplimiento(Request $request)
    {
        Gate::authorize('view-reports');
        $user = auth()->user();

        $casosQuery = Caso::query();
        if ($user->tipo_usuario !== 'admin') {
            $cooperativaIds = $user->cooperativas->pluck('id');
            $casosQuery->whereIn('cooperativa_id', $cooperativaIds);
        }
        $casosQuery->when($request->filled('cooperativa_id'), fn($q) => $q->where('cooperativa_id', $request->input('cooperativa_id')));
        $casosQuery->when($request->filled('user_id'), fn($q) => $q->where('user_id', $request->input('user_id')));
        $casosQuery->when($request->filled('tipo_proceso'), fn($q) => $q->where('tipo_proceso', $request->input('tipo_proceso')));
        // ELIMINADO: Filtro estado_proceso
        $casosQuery->when($request->filled('fecha_desde'), fn($q) => $q->whereDate('created_at', '>=', $request->input('fecha_desde')));
        $casosQuery->when($request->filled('fecha_hasta'), fn($q) => $q->whereDate('created_at', '<=', $request->input('fecha_hasta')));

        $fallasQuery = ValidacionLegal::where('estado', 'incumple')
            ->whereIn('caso_id', (clone $casosQuery)->pluck('id'))
            ->with(['caso.deudor', 'caso.cooperativa'])
            ->orderByRaw("CASE WHEN nivel_riesgo = 'alto' THEN 1 WHEN nivel_riesgo = 'medio' THEN 2 WHEN nivel_riesgo = 'bajo' THEN 3 ELSE 4 END")
            ->latest('ultima_revision');

        $fileName = 'reporte_cumplimiento_' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(new CumplimientoReportExport($fallasQuery), $fileName);
    }
}