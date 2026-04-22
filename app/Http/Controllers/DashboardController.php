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
        $isAdmin = $user->tipo_usuario === 'admin';
        
        // Configuraciones de tablas (sin cambios)
        $dbConfig = [
            'caso' => ['table' => 'pagos_caso', 'amount' => 'monto_pagado', 'date' => 'fecha_pago', 'fk' => 'caso_id'],
            'contrato' => ['table' => 'contrato_pagos', 'amount' => 'valor', 'date' => 'fecha']
        ];

        $currentPeriod = $this->getPeriodFromRequest($request, 'current');
        $previousPeriod = $this->getPeriodFromRequest($request, 'previous');

        // --- FILTRO DE SEGURIDAD ---
        // Si no es admin, solo calculamos KPIs para SU ID de usuario
        $targetUserId = $isAdmin ? null : $user->id;

        $currentStats = $this->calculateKpis($currentPeriod, $request->input('cooperativa_id'), $dbConfig, false, $targetUserId);
        $previousStats = $this->calculateKpis($previousPeriod, $request->input('cooperativa_id'), $dbConfig, true, $targetUserId);
        
        $kpis = [
            'saldo_bajo_gestion' => $this->formatKpiWithTrend($currentStats['saldo_total_activo'], $previousStats['saldo_total_activo']),
            'tasa_recuperacion'  => $this->formatKpiWithTrend($currentStats['tasa_recuperacion'], $previousStats['tasa_recuperacion']),
            'casos_asignados'    => $this->formatKpiWithTrend($currentStats['casos_activos'], $previousStats['casos_activos']),
            'casos_cerrados'     => $this->formatKpiWithTrend($currentStats['casos_cerrados'], $previousStats['casos_cerrados']),
        ];

        $chartData = $this->getUnifiedChartData($currentPeriod, $request->input('cooperativa_id'), $dbConfig, $targetUserId);
        
        // El ranking solo se muestra completo al Admin. El gestor solo ve su dato.
        $ranking = $isAdmin 
            ? $this->getUnifiedRanking($currentPeriod, $request->input('cooperativa_id'), $dbConfig)
            : $this->getUnifiedRanking($currentPeriod, $request->input('cooperativa_id'), $dbConfig, $user->id);

        return [
            'kpis' => $kpis,
            'chartData' => $chartData,
            'ranking' => $ranking,
            'cooperativas' => Cooperativa::orderBy('nombre')->get(['id', 'nombre']),
            'filters' => $request->only(['cooperativa_id', 'fecha_desde', 'fecha_hasta']),
            'userRole' => $user->tipo_usuario,
        ];
    }

    private function calculateKpis(array $period, ?int $cooperativaId, array $config, bool $isHistorical = false, ?int $userId = null): array
    {
        $baseQuery = Caso::query()
            ->when($cooperativaId, fn($q) => $q->where('cooperativa_id', $cooperativaId))
            ->when($userId, fn($q) => $q->where('user_id', $userId));

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

    private function getUnifiedChartData(array $period, ?int $cooperativaId, array $config, ?int $userId = null): array
    {
        $baseQuery = Caso::query()
            ->when($cooperativaId, fn($q) => $q->where('cooperativa_id', $cooperativaId))
            ->when($userId, fn($q) => $q->where('user_id', $userId));

        // 1. Distribución por etapas
        $casosPorEstado = [];
        if (Schema::hasColumn('casos', 'etapa_procesal')) {
            $casosPorEstado = (clone $baseQuery)
                ->select('etapa_procesal', DB::raw('count(*) as total'))
                ->reorder()
                ->groupBy('etapa_procesal')
                ->orderByDesc('total')
                ->get()
                ->mapWithKeys(fn($item) => [($item->etapa_procesal ?: 'Sin Etapa') => $item->total]);
        }

        // 2. Recuperación mensual (Lógica Blindada)
        $isPgsql = DB::getDriverName() === 'pgsql';
        $oneYearAgo = Carbon::now()->subMonths(11)->startOfMonth();

        // Recuperación de Pagos de Casos
        $qCasos = DB::table('pagos_caso')
            ->select(
                DB::raw($isPgsql ? "TO_CHAR(fecha_pago, 'YYYY-MM') as mes" : "DATE_FORMAT(fecha_pago, '%Y-%m') as mes"),
                DB::raw("SUM(monto_pagado) as total")
            )
            ->where('fecha_pago', '>=', $oneYearAgo);

        if ($userId || $cooperativaId) {
            $qCasos->join('casos', 'pagos_caso.caso_id', '=', 'casos.id')
                   ->when($userId, fn($q) => $q->where('casos.user_id', $userId))
                   ->when($cooperativaId, fn($q) => $q->where('casos.cooperativa_id', $cooperativaId));
        }
        $dataCasos = $qCasos->groupBy('mes')->pluck('total', 'mes');

        // Recuperación de Pagos de Contratos
        $qContratos = DB::table('contrato_pagos')
            ->select(
                DB::raw($isPgsql ? "TO_CHAR(fecha, 'YYYY-MM') as mes" : "DATE_FORMAT(fecha, '%Y-%m') as mes"),
                DB::raw("SUM(valor) as total")
            )
            ->where('fecha', '>=', $oneYearAgo);

        if ($userId || $cooperativaId) {
            $qContratos->join('contratos', 'contrato_pagos.contrato_id', '=', 'contratos.id')
                       ->join('casos', 'contratos.caso_id', '=', 'casos.id')
                       ->when($userId, fn($q) => $q->where('casos.user_id', $userId))
                       ->when($cooperativaId, fn($q) => $q->where('casos.cooperativa_id', $cooperativaId));
        }
        $dataContratos = $qContratos->groupBy('mes')->pluck('total', 'mes');

        // Unificar resultados
        $recuperacionTotal = [];
        for ($i = 11; $i >= 0; $i--) {
            $monthKey = Carbon::now()->subMonths($i)->format('Y-m');
            $total = ($dataCasos[$monthKey] ?? 0) + ($dataContratos[$monthKey] ?? 0);
            $recuperacionTotal[$monthKey] = (float)$total;
        }

        return [
            'casosPorEstado' => $casosPorEstado,
            'recuperacionPorMes' => $recuperacionTotal,
        ];
    }

    private function getUnifiedRanking(array $period, ?int $cooperativaId, array $config, ?int $userId = null)
    {
        $usersStats = [];

        $cfgCaso = $config['caso'];
        if (Schema::hasTable($cfgCaso['table'])) {
            $rankingCasos = DB::table('users')
                ->join('casos', 'users.id', '=', 'casos.user_id')
                ->join($cfgCaso['table'], 'casos.id', '=', "{$cfgCaso['table']}.{$cfgCaso['fk']}")
                ->select('users.id', 'users.name', DB::raw("SUM({$cfgCaso['table']}.{$cfgCaso['amount']}) as total"))
                ->whereBetween("{$cfgCaso['table']}.{$cfgCaso['date']}", [$period['start'], $period['end']])
                ->when($userId, fn($q) => $q->where('users.id', $userId))
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
                ->when($userId, fn($q) => $q->where('users.id', $userId))
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
            ->take($userId ? 1 : 3) // Si es un usuario específico, solo devolvemos su dato. Si es admin, el top 3.
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