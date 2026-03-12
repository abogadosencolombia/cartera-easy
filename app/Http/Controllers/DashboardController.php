<?php

namespace App\Http\Controllers;

use App\Models\Caso;
use App\Models\Cooperativa;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $props = [];

        try {
            if ($user->tipo_usuario === 'cliente' && $user->persona_id) {
                $props = $this->getClientDashboardData($user);
            } else {
                $props = $this->getAdminDashboardData($user, $request);
            }
        } catch (\Exception $e) {
            report($e);
            return Inertia::render('Dashboard/Index', [
                'kpis' => null,
                'chartData' => null,
                'ranking' => [],
                'cooperativas' => Cooperativa::orderBy('nombre')->get(['id', 'nombre']),
                'filters' => $request->only(['cooperativa_id', 'fecha_desde', 'fecha_hasta']),
                'userRole' => $user->tipo_usuario,
                'serverError' => 'Error de cálculo: ' . $e->getMessage()
            ]);
        }

        $props['serverError'] = null;
        return Inertia::render('Dashboard/Index', $props);
    }

    private function getAdminDashboardData(User $user, Request $request): array
    {
        $cfg = config('cartera', []);
        
        $T_PAGOS_CASO = 'pagos_caso';
        $Col_MONTO_CASO = 'monto_pagado';
        $Col_FECHA_CASO = 'fecha_pago';
        $Fk_CASO = 'caso_id';

        $T_PAGOS_CONTRATO = 'contrato_pagos';
        $Col_MONTO_CONTRATO = 'valor';
        $Col_FECHA_CONTRATO = 'fecha';
        
        $dbConfig = [
            'caso' => ['table' => $T_PAGOS_CASO, 'amount' => $Col_MONTO_CASO, 'date' => $Col_FECHA_CASO, 'fk' => $Fk_CASO],
            'contrato' => ['table' => $T_PAGOS_CONTRATO, 'amount' => $Col_MONTO_CONTRATO, 'date' => $Col_FECHA_CONTRATO]
        ];

        $currentPeriod = $this->getPeriodFromRequest($request, 'current');
        $previousPeriod = $this->getPeriodFromRequest($request, 'previous');

        $currentStats = $this->calculateKpis($currentPeriod, $request->input('cooperativa_id'), $dbConfig);
        $previousStats = $this->calculateKpis($previousPeriod, $request->input('cooperativa_id'), $dbConfig, true);
        
        $kpis = [
            'saldo_total_activo' => $this->formatKpiWithTrend($currentStats['saldo_total_activo'], $previousStats['saldo_total_activo']),
            'tasa_recuperacion'  => $this->formatKpiWithTrend($currentStats['tasa_recuperacion'], $previousStats['tasa_recuperacion']),
            'casos_activos'      => $this->formatKpiWithTrend($currentStats['casos_activos'], $previousStats['casos_activos']),
            'casos_cerrados'     => $this->formatKpiWithTrend($currentStats['casos_cerrados'], $previousStats['casos_cerrados']),
        ];

        $chartData = $this->getUnifiedChartData($currentPeriod, $request->input('cooperativa_id'), $dbConfig);
        $ranking = $this->getUnifiedRanking($currentPeriod, $request->input('cooperativa_id'), $dbConfig);

        return [
            'kpis' => $kpis,
            'chartData' => $chartData,
            'ranking' => $ranking,
            'cooperativas' => Cooperativa::orderBy('nombre')->get(['id', 'nombre']),
            'filters' => $request->only(['cooperativa_id', 'fecha_desde', 'fecha_hasta']),
            'userRole' => $user->tipo_usuario,
        ];
    }

    private function calculateKpis(array $period, ?int $cooperativaId, array $config, bool $isHistorical = false): array
    {
        $baseQuery = Caso::query()
            ->when($cooperativaId, fn($q) => $q->where('cooperativa_id', $cooperativaId));

        if ($isHistorical) {
            $baseQuery->where('fecha_apertura', '<=', $period['end']);
        }

        // --- LÓGICA DE ESTADO (CRÍTICA) ---
        // Activos: NO tienen nota de cierre.
        // Cerrados: SI tienen nota de cierre.
        
        $casosActivosQuery = clone $baseQuery;
        $casosCerradosTotalQuery = clone $baseQuery;

        // 1. Activos: Excluir estrictamente los que tienen nota de cierre
        $casosActivosQuery->whereNull('nota_cierre'); 
        
        // Adicionalmente, si la etapa dice "Cerrado", también lo sacamos de activos
        if (Schema::hasColumn('casos', 'etapa_procesal')) {
             $casosActivosQuery->where(function($q) {
                 $q->where('etapa_procesal', 'not ilike', '%cerrado%')
                   ->where('etapa_procesal', 'not ilike', '%terminado%')
                   ->where('etapa_procesal', 'not ilike', '%archivado%')
                   ->orWhereNull('etapa_procesal');
             });
        }

        // 2. Cerrados: Incluir los que tienen nota de cierre
        $casosCerradosTotalQuery->where(function($q) {
             $q->whereNotNull('nota_cierre'); // El cierre manual manda
             
             if (Schema::hasColumn('casos', 'etapa_procesal')) {
                 $q->orWhere(function($sub) {
                     $sub->where('etapa_procesal', 'ilike', '%cerrado%')
                         ->orWhere('etapa_procesal', 'ilike', '%terminado%')
                         ->orWhere('etapa_procesal', 'ilike', '%archivado%');
                 });
             }
        });

        $countActivos = $casosActivosQuery->count();
        $montoTotalDeudaActiva = $casosActivosQuery->sum('monto_total');
        $countCerradosTotal = $casosCerradosTotalQuery->count();

        // --- CÁLCULO DE FLUJO (Sin cambios) ---
        $totalRecuperado = 0;

        $cfgCaso = $config['caso'];
        if (Schema::hasTable($cfgCaso['table'])) {
            $q = DB::table($cfgCaso['table'])
                ->whereBetween($cfgCaso['date'], [$period['start'], $period['end']]);
            if ($cooperativaId) {
                $q->join('casos', $cfgCaso['table'].'.'.$cfgCaso['fk'], '=', 'casos.id')
                  ->where('casos.cooperativa_id', $cooperativaId);
            }
            $totalRecuperado += $q->sum($cfgCaso['amount']);
        }

        $cfgContrato = $config['contrato'];
        if (Schema::hasTable($cfgContrato['table']) && Schema::hasTable('contratos')) {
             $q = DB::table($cfgContrato['table'])
                ->whereBetween($cfgContrato['table'].'.'.$cfgContrato['date'], [$period['start'], $period['end']]);
             if ($cooperativaId) {
                 $q->join('contratos', $cfgContrato['table'].'.contrato_id', '=', 'contratos.id')
                   ->join('casos', 'contratos.caso_id', '=', 'casos.id')
                   ->where('casos.cooperativa_id', $cooperativaId);
             }
             $totalRecuperado += $q->sum($cfgContrato['table'].'.'.$cfgContrato['amount']);
        }

        $tasaRecuperacion = ($montoTotalDeudaActiva > 0) ? round(($totalRecuperado / $montoTotalDeudaActiva) * 100, 2) : 0;

        return [
            'casos_activos' => $countActivos,
            'saldo_total_activo' => $montoTotalDeudaActiva,
            'tasa_recuperacion' => $tasaRecuperacion, 
            'casos_cerrados' => $countCerradosTotal, 
        ];
    }

    private function getUnifiedChartData(array $period, ?int $cooperativaId, array $config): array
    {
        $baseQuery = Caso::query()
            ->whereBetween('fecha_apertura', [$period['start'], $period['end']])
            ->when($cooperativaId, fn($q) => $q->where('cooperativa_id', $cooperativaId));

        $casosPorEstado = [];
        if (Schema::hasColumn('casos', 'etapa_procesal')) {
            $casosPorEstado = (clone $baseQuery)
                ->select('etapa_procesal', DB::raw('count(*) as total'))
                ->reorder()
                ->groupBy('etapa_procesal')
                ->orderByDesc('total')
                ->get()
                ->mapWithKeys(fn($item) => [$item->etapa_procesal ?: 'Sin Etapa' => $item->total]);
        } else {
            $casosPorEstado = ['Total' => (clone $baseQuery)->count()];
        }

        $recuperacionCasos = collect([]);
        $recuperacionContratos = collect([]);

        $cfgCaso = $config['caso'];
        if (Schema::hasTable($cfgCaso['table'])) {
            $recuperacionCasos = DB::table($cfgCaso['table'])
                ->select(DB::raw("TO_CHAR({$cfgCaso['date']}, 'YYYY-MM') as mes"), DB::raw("SUM({$cfgCaso['amount']}) as total"))
                ->where("{$cfgCaso['date']}", '>=', Carbon::now()->subYear())
                ->groupBy(DB::raw("TO_CHAR({$cfgCaso['date']}, 'YYYY-MM')"))
                ->orderBy(DB::raw("TO_CHAR({$cfgCaso['date']}, 'YYYY-MM')"))
                ->get()->pluck('total', 'mes');
        }

        $cfgContrato = $config['contrato'];
        if (Schema::hasTable($cfgContrato['table']) && Schema::hasTable('contratos')) {
             $recuperacionContratos = DB::table($cfgContrato['table'])
                ->select(DB::raw("TO_CHAR({$cfgContrato['date']}, 'YYYY-MM') as mes"), DB::raw("SUM({$cfgContrato['amount']}) as total"))
                ->where("{$cfgContrato['date']}", '>=', Carbon::now()->subYear())
                ->groupBy(DB::raw("TO_CHAR({$cfgContrato['date']}, 'YYYY-MM')"))
                ->orderBy(DB::raw("TO_CHAR({$cfgContrato['date']}, 'YYYY-MM')"))
                ->get()->pluck('total', 'mes');
        }

        $todosLosMeses = $recuperacionCasos->keys()->merge($recuperacionContratos->keys())->unique()->sort();
        $recuperacionTotal = $todosLosMeses->mapWithKeys(function($mes) use ($recuperacionCasos, $recuperacionContratos) {
            return [$mes => $recuperacionCasos->get($mes, 0) + $recuperacionContratos->get($mes, 0)];
        });

        return [
            'casosPorEstado' => $casosPorEstado,
            'recuperacionPorMes' => $recuperacionTotal,
        ];
    }

    private function getUnifiedRanking(array $period, ?int $cooperativaId, array $config)
    {
        $usersStats = [];

        $cfgCaso = $config['caso'];
        if (Schema::hasTable($cfgCaso['table'])) {
            $rankingCasos = DB::table('users')
                ->join('casos', 'users.id', '=', 'casos.user_id')
                ->join($cfgCaso['table'], 'casos.id', '=', "{$cfgCaso['table']}.{$cfgCaso['fk']}")
                ->select('users.id', 'users.name', DB::raw("SUM({$cfgCaso['table']}.{$cfgCaso['amount']}) as total"))
                ->whereBetween("{$cfgCaso['table']}.{$cfgCaso['date']}", [$period['start'], $period['end']])
                ->when($cooperativaId, fn($q) => $q->where('casos.cooperativa_id', $cooperativaId))
                ->groupBy('users.id', 'users.name')
                ->get();

            foreach ($rankingCasos as $r) {
                if (!isset($usersStats[$r->id])) $usersStats[$r->id] = ['id' => $r->id, 'name' => $r->name, 'total_recuperado' => 0];
                $usersStats[$r->id]['total_recuperado'] += (float)$r->total;
            }
        }

        $cfgContrato = $config['contrato'];
        if (Schema::hasTable($cfgContrato['table']) && Schema::hasTable('contratos')) {
             $rankingContratos = DB::table('users')
                ->join('casos', 'users.id', '=', 'casos.user_id')
                ->join('contratos', 'casos.id', '=', 'contratos.caso_id')
                ->join($cfgContrato['table'], 'contratos.id', '=', "{$cfgContrato['table']}.contrato_id")
                ->select('users.id', 'users.name', DB::raw("SUM({$cfgContrato['table']}.{$cfgContrato['amount']}) as total"))
                ->whereBetween("{$cfgContrato['table']}.{$cfgContrato['date']}", [$period['start'], $period['end']])
                ->when($cooperativaId, fn($q) => $q->where('casos.cooperativa_id', $cooperativaId))
                ->groupBy('users.id', 'users.name')
                ->get();

            foreach ($rankingContratos as $r) {
                if (!isset($usersStats[$r->id])) $usersStats[$r->id] = ['id' => $r->id, 'name' => $r->name, 'total_recuperado' => 0];
                $usersStats[$r->id]['total_recuperado'] += (float)$r->total;
            }
        }

        return collect($usersStats)
            ->where('total_recuperado', '>', 0)
            ->sortByDesc('total_recuperado')
            ->take(3)
            ->values()
            ->all();
    }

    private function getClientDashboardData(User $user): array
    {
        $cfg = config('cartera', []);
        $T_PAGOS = $cfg['recoveries_table'] ?? 'pago_casos';
        $Col_MONTO = $this->pickColumn($T_PAGOS, $cfg['recovery_amount_candidates'] ?? [], 'monto_pagado');
        $Fk_CASO = $this->pickColumn($T_PAGOS, $cfg['recovery_case_fk_candidates'] ?? [], 'caso_id');

        $casosComoCodeudorIds = collect([]);
        if (Schema::hasTable('caso_codeudor')) {
            $casosComoCodeudorIds = DB::table('caso_codeudor')->where('codeudor_id', $user->persona_id)->pluck('caso_id');
        }

        $casosQuery = Caso::query()->where(function ($q) use ($user, $casosComoCodeudorIds) {
            $q->where('deudor_id', $user->persona_id);
            if ($casosComoCodeudorIds->isNotEmpty()) $q->orWhereIn('id', $casosComoCodeudorIds);
        });
        
        $casosActivosQuery = clone $casosQuery;
        // Cliente también ve si tiene nota de cierre
        $casosActivosQuery->whereNull('nota_cierre');

        if (Schema::hasColumn('casos', 'etapa_procesal')) {
             $casosActivosQuery->where(function($q) {
                 $q->where('etapa_procesal', 'not ilike', '%cerrado%')
                   ->where('etapa_procesal', 'not ilike', '%terminado%')
                   ->orWhereNull('etapa_procesal');
             });
        }
        
        $casosActivosIds = (clone $casosActivosQuery)->pluck('id');
        $montoTotalDeuda = (clone $casosActivosQuery)->sum('monto_total') ?? 0;
        
        $totalPagado = 0;
        if (Schema::hasTable($T_PAGOS) && $casosActivosIds->isNotEmpty()) {
            $totalPagado = DB::table($T_PAGOS)->whereIn($Fk_CASO, $casosActivosIds)->sum($Col_MONTO) ?? 0;
        }
        
        return [
            'kpis' => [
                'saldo_total_pendiente' => max(0, $montoTotalDeuda - $totalPagado),
                'casos_activos' => $casosActivosIds->count(),
            ],
            'userRole' => 'cliente',
            'chartData' => null, 'ranking' => [], 'cooperativas' => [], 'filters' => null,
        ];
    }

    private function getPeriodFromRequest(Request $request, string $type): array
    {
        $from = $request->filled('fecha_desde') ? Carbon::parse($request->input('fecha_desde')) : Carbon::now()->subDays(365);
        $to = $request->filled('fecha_hasta') ? Carbon::parse($request->input('fecha_hasta')) : Carbon::now();

        if ($type === 'current') return ['start' => $from->copy()->startOfDay(), 'end' => $to->copy()->endOfDay()];
        
        $days = $to->diffInDays($from);
        return ['start' => $from->copy()->subDays($days)->startOfDay(), 'end' => $from->copy()->subDay()->endOfDay()];
    }

    private function formatKpiWithTrend($curr, $prev): array
    {
        $trend = ($prev && $prev > 0) ? round((($curr - $prev) / abs($prev)) * 100, 1) : ($curr > 0 ? 100 : 0);
        return ['value' => $curr, 'trend' => $trend, 'direction' => $trend >= 0 ? 'up' : 'down'];
    }
    
    private function pickColumn(string $table, array $candidates, ?string $fallback = null): string
    {
        if (!Schema::hasTable($table)) return $fallback ?? $candidates[0] ?? 'id';
        foreach ($candidates as $col) {
            if (Schema::hasColumn($table, $col)) return $col;
        }
        return $fallback ?? $candidates[0] ?? 'id';
    }
}