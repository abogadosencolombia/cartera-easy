<?php

namespace App\Http\Controllers;

use App\Models\Caso;
use App\Models\ProcesoRadicado;
use App\Models\User;
use App\Models\ContratoCuota;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;
use App\Services\ImpulsoProcesalService;

class AnalyticsController extends Controller
{
    public function index(ImpulsoProcesalService $impulsoService)
    {
        // 0. DEFINICIONES DE ESTADO... (ya existente)
        $estadosCierreRad = ['CERRADO', 'ARCHIVADO', 'FINALIZADO', 'TERMINADO'];
        $estadosCierreCasos = ['cerrado', 'finalizado', 'archivado'];

        // 1. OBTENER RADAR DE ACCIÓN (INTELIGENCIA)
        $radarAcciones = $impulsoService->obtenerRadarDeAccion(6);

        // 2. SALUD DEL DESPACHO GLOBAL (UNIFICADA)
        $hoy = Carbon::today();
        $cincoDias = $hoy->copy()->subDays(5);
        $veinteDias = $hoy->copy()->subDays(20);

        // Radicados: Basado en fecha_proxima_revision
        $radAlDia = ProcesoRadicado::paraSeguimiento()
                        ->where('fecha_proxima_revision', '>', $hoy->copy()->addDays(2))
                        ->count();
        $radEnRiesgo = ProcesoRadicado::paraSeguimiento()
                        ->whereBetween('fecha_proxima_revision', [$hoy, $hoy->copy()->addDays(2)])
                        ->count();
        $radVencidos = ProcesoRadicado::paraSeguimiento()
                        ->where('fecha_proxima_revision', '<', $hoy)
                        ->count();

        // Casos: Basado en updated_at (Inactividad)
        $casosAlDia = Caso::paraSeguimiento()
                        ->where('updated_at', '>', $cincoDias)
                        ->count();
        $casosEnRiesgo = Caso::paraSeguimiento()
                        ->whereBetween('updated_at', [$veinteDias, $cincoDias])
                        ->count();
        $casosVencidos = Caso::paraSeguimiento()
                        ->where('updated_at', '<', $veinteDias)
                        ->count();

        $salud = [
            'al_dia' => $radAlDia + $casosAlDia,
            'en_riesgo' => $radEnRiesgo + $casosEnRiesgo,
            'vencidos' => $radVencidos + $casosVencidos,
        ];

        // 2b. VIABILIDAD JURÍDICA GLOBAL (NUEVO)
        $viabilidadProcesos = ProcesoRadicado::paraSeguimiento()
            ->select('viabilidad_estado', DB::raw('count(*) as total'))
            ->groupBy('viabilidad_estado')
            ->get();

        $viabilidadCasos = Caso::paraSeguimiento()
            ->select('viabilidad_estado', DB::raw('count(*) as total'))
            ->groupBy('viabilidad_estado')
            ->get();

        $viabilidadMerged = collect($viabilidadProcesos)
            ->concat($viabilidadCasos)
            ->groupBy(fn($item) => $item->viabilidad_estado ?: 'pendiente')
            ->map(fn($group) => $group->sum('total'));

        $chartViabilidad = [
            'verde' => $viabilidadMerged->get('verde', 0),
            'amarillo' => $viabilidadMerged->get('amarillo', 0),
            'rojo' => $viabilidadMerged->get('rojo', 0),
            'pendiente' => $viabilidadMerged->get('pendiente', 0),
        ];

        // 2c. CARGA DE TRABAJO POR ABOGADO (UNIFICADA)
        $cargaAbogados = User::where('estado_activo', true)
            ->whereIn('tipo_usuario', ['admin', 'abogado', 'gestor'])
            ->withCount(['procesos' => function($query) {
                $query->paraSeguimiento();
            }])
            ->withCount(['casos' => function($query) {
                $query->paraSeguimiento();
            }])
            ->get(['id', 'name', 'procesos_count', 'casos_count'])
            ->map(function($user) {
                return [
                    'name' => $user->name,
                    'procesos_count' => $user->procesos_count + $user->casos_count
                ];
            })
            ->sortByDesc('procesos_count')
            ->values();

        // 3. FINANZAS: RECAUDO VS PROGRAMADO (Últimos 6 meses)
        $meses = [];
        $recaudado = [];
        $programado = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $meses[] = $date->translatedFormat('M y');
            
            $inicioMes = $date->copy()->startOfMonth();
            $finMes = $date->copy()->endOfMonth();

            $sumProgramado = ContratoCuota::whereBetween('fecha_vencimiento', [$inicioMes, $finMes])->sum('valor');
            $sumRecaudado = DB::table('contrato_pagos')
                            ->whereBetween('fecha', [$inicioMes, $finMes])
                            ->sum('valor');

            $programado[] = (float) $sumProgramado;
            $recaudado[] = (float) $sumRecaudado;
        }

        // 4. CRECIMIENTO DE NUEVOS CASOS (UNIFICADO)
        $crecimiento = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $countProcesos = ProcesoRadicado::whereMonth('created_at', $date->month)
                                            ->whereYear('created_at', $date->year)
                                            ->count();
            $countCasos = Caso::whereMonth('created_at', $date->month)
                               ->whereYear('created_at', $date->year)
                               ->count();
            
            $crecimiento[] = $countProcesos + $countCasos;
        }

        // 5. ESPECIALIDADES MÁS COMUNES (UNIFICADO)
        $especialidadesProcesos = DB::table('proceso_radicados')
            ->join('tipos_proceso', 'proceso_radicados.tipo_proceso_id', '=', 'tipos_proceso.id')
            ->select('tipos_proceso.nombre', DB::raw('count(*) as total'))
            ->groupBy('tipos_proceso.nombre')
            ->get();

        $especialidadesCasos = DB::table('casos')
            ->join('especialidades_juridicas', 'casos.especialidad_id', '=', 'especialidades_juridicas.id')
            ->select('especialidades_juridicas.nombre', DB::raw('count(*) as total'))
            ->groupBy('especialidades_juridicas.nombre')
            ->get();

        $mergedEspecialidades = collect($especialidadesProcesos)
            ->concat($especialidadesCasos)
            ->groupBy('nombre')
            ->map(fn($group) => ['nombre' => $group[0]->nombre, 'total' => $group->sum('total')])
            ->sortByDesc('total')
            ->take(5)
            ->values();

        // 6. RANKING DE JUZGADOS
        $juzgados = DB::table('proceso_radicados')
            ->join('juzgados', 'proceso_radicados.juzgado_id', '=', 'juzgados.id')
            ->select('juzgados.nombre', DB::raw('count(*) as total'))
            ->groupBy('juzgados.nombre')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        // 7. TASA DE CIERRE (Últimos 30 días)
        $creados30 = ProcesoRadicado::where('created_at', '>=', now()->subDays(30))->count() + Caso::where('created_at', '>=', now()->subDays(30))->count();
        $cerrados30 = ProcesoRadicado::where('estado', 'CERRADO')->where('updated_at', '>=', now()->subDays(30))->count() + Caso::where('estado_proceso', 'cerrado')->where('updated_at', '>=', now()->subDays(30))->count();

        // 8. ACTIVIDAD RECIENTE (Paginación Real - Sin Logins)
        $actividadReciente = \App\Models\AuditoriaEvento::with('usuario:id,name')
            ->whereNotNull('user_id')
            ->where('evento', '!=', 'LOGIN_EXITOSO')
            ->latest()
            ->paginate(10, ['*'], 'page_actividad')
            ->through(function($evento) {
                // Identificar módulo con nombres descriptivos exactos
                $modulo = match($evento->auditable_type) {
                    'App\Models\Caso' => 'Casos Cooperativas',
                    'App\Models\ProcesoRadicado' => 'Casos Abogados en Colombia',
                    'App\Models\DocumentoCaso', 'App\Models\PersonaDocumento' => 'Casos Cooperativas',
                    'App\Models\DocumentoProceso', 'App\Models\Actuacion' => 'Casos Abogados en Colombia',
                    'App\Models\Contrato', 'App\Models\ContratoCuota', 'App\Models\ContratoPago' => 'Finanzas',
                    'App\Models\Persona', 'App\Models\Cooperativa' => 'Directorio',
                    default => 'Sistema'
                };

                return [
                    'id' => $evento->id,
                    'usuario' => $evento->usuario?->name ?? 'Sistema',
                    'evento' => str_replace(['_'], ' ', $evento->evento),
                    'descripcion' => $evento->descripcion_breve,
                    'modulo' => $modulo,
                    'fecha' => $evento->created_at->diffForHumans(),
                    'hora' => $evento->created_at->format('h:i A'),
                ];
            })
            ->withQueryString();

        // 9. RANKING DE PRODUCTIVIDAD (Acciones totales hoy - Sin Logins)
        $productividadHoy = DB::table('auditoria_eventos')
            ->join('users', 'auditoria_eventos.user_id', '=', 'users.id')
            ->whereDate('auditoria_eventos.created_at', Carbon::today())
            ->where('auditoria_eventos.evento', '!=', 'LOGIN_EXITOSO')
            ->select('users.name', DB::raw('count(*) as total'))
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total')
            ->get();

        return Inertia::render('Dashboard/Analytics', [
            'stats' => [
                'total_activos' => ProcesoRadicado::paraSeguimiento()->count() + 
                                   Caso::paraSeguimiento()->count(),
                'total_cerrados' => ProcesoRadicado::whereIn('estado', $estadosCierreRad)->count() + 
                                    Caso::whereIn('estado_proceso', $estadosCierreCasos)->count(),
                'tasa_cierre' => $creados30 > 0 ? round(($cerrados30 / $creados30) * 100, 1) : 0,
                'recaudo_mes' => number_format(count($recaudado) > 0 ? end($recaudado) : 0, 0, ',', '.'),
            ],
            'charts' => [
                'radar' => $radarAcciones,
                'salud' => $salud,
                'carga' => $cargaAbogados,
                'finanzas' => [
                    'labels' => $meses,
                    'recaudado' => $recaudado,
                    'programado' => $programado,
                ],
                'crecimiento' => [
                    'labels' => $meses,
                    'data' => $crecimiento,
                ],
                'especialidades' => $mergedEspecialidades,
                'juzgados' => $juzgados,
                'actividad' => $actividadReciente,
                'productividad' => $productividadHoy,
            ]
        ]);
    }
}
