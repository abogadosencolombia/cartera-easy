<?php

namespace App\Http\Controllers;

use App\Events\ReporteExportado;
use App\Models\Caso;
use App\Models\Cooperativa;
use App\Exports\CasosReportExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\PagoCaso;
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
        $pagosQuery = PagoCaso::query();

        if ($user->tipo_usuario !== 'admin') {
            $cooperativaIds = $user->cooperativas->pluck('id');
            $casosQuery->whereIn('cooperativa_id', $cooperativaIds);
        }
        
        // --- APLICACIÓN DE FILTROS DINÁMICOS ---
        $casosQuery->when($request->filled('cooperativa_id'), fn($q) => $q->where('cooperativa_id', $request->input('cooperativa_id')));
        $casosQuery->when($request->filled('user_id'), fn($q) => $q->where('user_id', $request->input('user_id')));
        $casosQuery->when($request->filled('tipo_proceso'), fn($q) => $q->where('tipo_proceso', $request->input('tipo_proceso')));
        $casosQuery->when($request->filled('estado_proceso'), fn($q) => $q->where('estado_proceso', $request->input('estado_proceso')));
        $casosQuery->when($request->filled('fecha_desde'), fn($q) => $q->whereDate('created_at', '>=', $request->input('fecha_desde')));
        $casosQuery->when($request->filled('fecha_hasta'), fn($q) => $q->whereDate('created_at', '<=', $request->input('fecha_hasta')));

        $pagosQuery->whereHas('caso', function ($q) use ($casosQuery) {
            $q->whereIn('id', (clone $casosQuery)->pluck('id'));
        });

        // --- CÁLCULO DE KPIs ESTRATÉGICOS (Corregido para PostgreSQL) ---
        $casosPorEstado = (clone $casosQuery)->select('estado_proceso', DB::raw('count(*) as total'))->groupBy('estado_proceso')->get()->pluck('total', 'estado_proceso');
        $totalCasosActivos = $casosPorEstado->sum() - ($casosPorEstado['cerrado'] ?? 0);
        $carteraEnMora = (clone $casosQuery)->where('fecha_vencimiento', '<', now())->where('estado_proceso', '!=', 'cerrado')->sum('monto_total');
        $totalRecuperado = (clone $pagosQuery)->sum('monto_pagado');
        $promedioDiasRecuperacion = (clone $casosQuery)->where('estado_proceso', 'cerrado')->whereNotNull('fecha_ultimo_pago')->select(DB::raw('AVG(fecha_ultimo_pago - created_at) as promedio'))->value('promedio');
        $casosActivosQuery = (clone $casosQuery)->where('estado_proceso', '!=', 'cerrado');
        $casosSinPagare = (clone $casosActivosQuery)->whereDoesntHave('documentos', fn ($q) => $q->where('tipo_documento', 'pagaré'))->count();
        $porcentajeSinPagare = ($totalCasosActivos > 0) ? ($casosSinPagare / $totalCasosActivos) * 100 : 0;
        $casosInactivos = (clone $casosActivosQuery)->where('updated_at', '<', Carbon::now()->subDays(60))->count();
        $casosConDocumentosFaltantes = (clone $casosActivosQuery)
        ->whereHas('validacionesLegales', function ($query) {
            $query->where('tipo', 'documento_requerido')->where('estado', 'incumple');
        })->count();
        $casosLongevos = (clone $casosActivosQuery)->where('fecha_apertura', '<', Carbon::now()->subYear())->count();
        $notificacionesQuery = NotificacionCaso::whereHas('caso', function ($q) use ($casosQuery) { $q->whereIn('id', (clone $casosQuery)->pluck('id')); });
        $alertasPorTipo = (clone $notificacionesQuery)->whereNull('atendida_en')->groupBy('tipo')->select('tipo', DB::raw('count(*) as total'))->pluck('total', 'tipo');
        $alertasVencidas = (clone $notificacionesQuery)->whereNull('atendida_en')->where('created_at', '<', now()->subDays(7))->count();
        $totalAlertasActivas = (clone $notificacionesQuery)->whereNull('atendida_en')->count();
        $tendenciaSemanalAlertas = (clone $notificacionesQuery)->where('created_at', '>=', now()->subDays(7))->groupBy('date')->orderBy('date', 'ASC')->get([ DB::raw('DATE(created_at) as date'), DB::raw('count(*) as total') ]);
        $consolidadoCooperativa = null;
        if ($request->filled('cooperativa_id')) {
            $coopId = $request->input('cooperativa_id');
            $cooperativaSeleccionada = Cooperativa::find($coopId);
            if ($cooperativaSeleccionada) {
                $casosPorEstadoCoop = (clone $casosQuery)->select('estado_proceso', DB::raw('count(*) as total'))->groupBy('estado_proceso')->get()->pluck('total', 'estado_proceso');
                $consolidadoCooperativa = [ 'nombre' => $cooperativaSeleccionada->nombre, 'casos_activos' => $casosPorEstadoCoop->sum() - ($casosPorEstadoCoop['cerrado'] ?? 0), 'cartera_en_mora' => (clone $casosQuery)->where('fecha_vencimiento', '<', now())->where('estado_proceso', '!=', 'cerrado')->sum('monto_total'), 'total_recuperado' => (clone $pagosQuery)->sum('monto_pagado'), ];
            }
        }
        $pagosMensuales = (clone $pagosQuery)->select(DB::raw('EXTRACT(YEAR FROM fecha_pago) as anio, EXTRACT(MONTH FROM fecha_pago) as mes, SUM(monto_pagado) as total'))->where('fecha_pago', '>=', Carbon::now()->subYear())->groupBy('anio', 'mes')->orderBy('anio', 'asc')->orderBy('mes', 'asc')->get();
        $carteraPorEdad = (clone $casosQuery)->where('fecha_vencimiento', '<', now())->where('estado_proceso', '!=', 'cerrado')->select(DB::raw("CASE WHEN (CURRENT_DATE - fecha_vencimiento) BETWEEN 31 AND 60 THEN '31-60 días' WHEN (CURRENT_DATE - fecha_vencimiento) BETWEEN 61 AND 90 THEN '61-90 días' WHEN (CURRENT_DATE - fecha_vencimiento) BETWEEN 91 AND 120 THEN '91-120 días' WHEN (CURRENT_DATE - fecha_vencimiento) > 120 THEN '>120 días' ELSE '1-30 días' END as rango_mora, SUM(monto_total) as total"))->groupBy('rango_mora')->get()->pluck('total', 'rango_mora');
        $moraMensual = (clone $casosQuery)->select(DB::raw('EXTRACT(YEAR FROM fecha_vencimiento) as anio, EXTRACT(MONTH FROM fecha_vencimiento) as mes, SUM(monto_total) as total'))->where('fecha_vencimiento', '>=', Carbon::now()->subYear())->groupBy('anio', 'mes')->orderBy('anio', 'asc')->orderBy('mes', 'asc')->get();
        $rankingAbogados = User::whereIn('tipo_usuario', ['abogado', 'gestor'])->withCount(['casos as casos_count' => fn($q) => $q->whereIn('casos.id', (clone $casosQuery)->pluck('id'))])->withSum(['pagos as pagos_sum_monto_pagado' => fn($q) => $q->whereIn('pagos_caso.id', (clone $pagosQuery)->pluck('id'))], 'monto_pagado')->orderBy('pagos_sum_monto_pagado', 'desc')->limit(10)->get();
        $cooperativas = ($user->tipo_usuario === 'admin') ? Cooperativa::all(['id', 'nombre']) : $user->cooperativas;
        $abogadosYGestores = User::whereIn('tipo_usuario', ['abogado', 'gestor'])->get(['id', 'name']);
        
        // =================================================================
        // ===== LÓGICA INTEGRADA: REPORTE DE CUMPLIMIENTO LEGAL =====
        // =================================================================
        $baseValidacionesQuery = ValidacionLegal::whereIn('caso_id', (clone $casosQuery)->pluck('id'));
        $totalFallasActivas = (clone $baseValidacionesQuery)->where('estado', 'incumple')->count();
        $fallasPorRiesgo = (clone $baseValidacionesQuery)->where('estado', 'incumple')->select('nivel_riesgo', DB::raw('count(*) as total'))->groupBy('nivel_riesgo')->pluck('total', 'nivel_riesgo');
        $fallasPorCooperativa = (clone $baseValidacionesQuery)->where('validaciones_legales.estado', 'incumple')->join('casos', 'validaciones_legales.caso_id', '=', 'casos.id')->join('cooperativas', 'casos.cooperativa_id', '=', 'cooperativas.id')->select('cooperativas.nombre', DB::raw('count(*) as total_fallas'))->groupBy('cooperativas.nombre')->orderByDesc('total_fallas')->limit(5)->get();
        // Corregir FIELD() que no existe en PostgreSQL - usar CASE WHEN
        $listadoFallas = (clone $baseValidacionesQuery)->where('estado', 'incumple')->with(['caso.deudor', 'caso.cooperativa'])->orderByRaw("CASE WHEN nivel_riesgo = 'alto' THEN 1 WHEN nivel_riesgo = 'medio' THEN 2 WHEN nivel_riesgo = 'bajo' THEN 3 ELSE 4 END")->latest('ultima_revision')->get();
        // =================================================================

        return Inertia::render('Reportes/Dashboard', [
            'kpis' => [
                'casos_activos' => $totalCasosActivos, 'total_recuperado' => (float) $totalRecuperado, 'cartera_en_mora' => (float) $carteraEnMora, 'promedio_dias_recuperacion' => round($promedioDiasRecuperacion ?? 0), 'casos_sin_pagare' => $casosSinPagare, 'porcentaje_sin_pagare' => round($porcentajeSinPagare, 2), 'casos_inactivos' => $casosInactivos, 'casos_con_documentos_faltantes' => $casosConDocumentosFaltantes, 'casos_longevos' => $casosLongevos, 'total_alertas_activas' => $totalAlertasActivas, 'alertas_vencidas' => $alertasVencidas,
            ],
            'graficas' => [
                'pagos_mensuales' => $pagosMensuales, 'casos_por_estado' => $casosPorEstado, 'cartera_por_edad' => $carteraPorEdad, 'mora_mensual' => $moraMensual, 'alertas_por_tipo' => $alertasPorTipo, 'tendencia_semanal_alertas' => $tendenciaSemanalAlertas,
            ],
            'rankingAbogados' => $rankingAbogados,
            'consolidadoCooperativa' => $consolidadoCooperativa,
            'cooperativas' => $cooperativas,
            'abogadosYGestores' => $abogadosYGestores,
            'filtros' => $request->only(['cooperativa_id', 'user_id', 'tipo_proceso', 'estado_proceso', 'fecha_desde', 'fecha_hasta']),
            
            // --- PROPS PARA LA PESTAÑA DE CUMPLIMIENTO ---
            'statsCumplimiento' => [
                'totalFallasActivas' => $totalFallasActivas,
                'fallasPorRiesgo' => [ 'alto' => $fallasPorRiesgo->get('alto', 0), 'medio' => $fallasPorRiesgo->get('medio', 0), 'bajo' => $fallasPorRiesgo->get('bajo', 0), ],
                'fallasPorCooperativa' => $fallasPorCooperativa,
            ],
            'listadoFallas' => $listadoFallas,
        ]);
    }

    public function exportar(Request $request)
    {
        Gate::authorize('view-reports');
        $user = auth()->user();

        $casosQuery = Caso::with(['cooperativa', 'deudor', 'user']);

        $filtros = $request->only(['cooperativa_id', 'user_id', 'tipo_proceso', 'estado_proceso', 'fecha_desde', 'fecha_hasta']);

        if ($user->tipo_usuario !== 'admin') {
            $cooperativaIds = $user->cooperativas->pluck('id');
            $casosQuery->whereIn('cooperativa_id', $cooperativaIds);
        }

        $casosQuery->when($request->filled('cooperativa_id'), fn($q) => $q->where('cooperativa_id', $request->input('cooperativa_id')));
        $casosQuery->when($request->filled('user_id'), fn($q) => $q->where('user_id', $request->input('user_id')));
        $casosQuery->when($request->filled('tipo_proceso'), fn($q) => $q->where('tipo_proceso', 'like', '%' . $request->input('tipo_proceso') . '%'));
        $casosQuery->when($request->filled('estado_proceso'), fn($q) => $q->where('estado_proceso', 'like', '%' . $request->input('estado_proceso') . '%'));
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

    // =================================================================
    // ===== INICIO DEL NUEVO MÉTODO PARA EXPORTAR CUMPLIMIENTO =====
    // =================================================================
    public function exportarCumplimiento(Request $request)
    {
        Gate::authorize('view-reports');
        $user = auth()->user();

        // Replicamos la misma lógica de filtrado que en el método index
        $casosQuery = Caso::query();
        if ($user->tipo_usuario !== 'admin') {
            $cooperativaIds = $user->cooperativas->pluck('id');
            $casosQuery->whereIn('cooperativa_id', $cooperativaIds);
        }
        $casosQuery->when($request->filled('cooperativa_id'), fn($q) => $q->where('cooperativa_id', $request->input('cooperativa_id')));
        $casosQuery->when($request->filled('user_id'), fn($q) => $q->where('user_id', $request->input('user_id')));
        $casosQuery->when($request->filled('tipo_proceso'), fn($q) => $q->where('tipo_proceso', $request->input('tipo_proceso')));
        $casosQuery->when($request->filled('estado_proceso'), fn($q) => $q->where('estado_proceso', $request->input('estado_proceso')));
        $casosQuery->when($request->filled('fecha_desde'), fn($q) => $q->whereDate('created_at', '>=', $request->input('fecha_desde')));
        $casosQuery->when($request->filled('fecha_hasta'), fn($q) => $q->whereDate('created_at', '<=', $request->input('fecha_hasta')));

        // Construimos la consulta final para las fallas de cumplimiento
        $fallasQuery = ValidacionLegal::where('estado', 'incumple')
            ->whereIn('caso_id', (clone $casosQuery)->pluck('id'))
            ->with(['caso.deudor', 'caso.cooperativa'])
            ->orderByRaw("CASE WHEN nivel_riesgo = 'alto' THEN 1 WHEN nivel_riesgo = 'medio' THEN 2 WHEN nivel_riesgo = 'bajo' THEN 3 ELSE 4 END")
            ->latest('ultima_revision');

        $fileName = 'reporte_cumplimiento_' . now()->format('Y-m-d') . '.xlsx';

        // Usamos nuestra nueva clase Export para generar el archivo
        return Excel::download(new CumplimientoReportExport($fallasQuery), $fileName);
    }
    // =================================================================
    // ===== FIN DEL NUEVO MÉTODO ======================================
    // =================================================================
}