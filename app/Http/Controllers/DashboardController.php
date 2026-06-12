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
                'cooperativas' => $this->getDashboardCooperativas($user),
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
        $cooperativaId = $this->requestedCooperativaId($request);

        $dbConfig = [
            'caso' => ['table' => 'pagos_caso', 'amount' => 'monto_pagado', 'date' => 'fecha_pago', 'fk' => 'caso_id'],
            'contrato' => ['table' => 'contrato_pagos', 'amount' => 'valor', 'date' => 'fecha']
        ];

        $currentPeriod = $this->getPeriodFromRequest($request, 'current');
        $previousPeriod = $this->getPeriodFromRequest($request, 'previous');

        $currentStats = $this->calculateKpis($currentPeriod, $cooperativaId, $dbConfig, $user);
        $previousStats = $this->calculateKpis($previousPeriod, $cooperativaId, $dbConfig, $user, true);

        $kpis = [
            'saldo_bajo_gestion' => $this->formatKpiWithTrend($currentStats['saldo_total_activo'], $previousStats['saldo_total_activo']),
            'tasa_recuperacion'  => $this->formatKpiWithTrend($currentStats['tasa_recuperacion'], $previousStats['tasa_recuperacion']),
            'casos_asignados'    => $this->formatKpiWithTrend($currentStats['casos_activos'], $previousStats['casos_activos']),
            'casos_cerrados'     => $this->formatKpiWithTrend($currentStats['casos_cerrados'], $previousStats['casos_cerrados']),
        ];

        $chartData = $this->getUnifiedChartData($currentPeriod, $cooperativaId, $dbConfig, $user);

        $ranking = $this->getUnifiedRanking($currentPeriod, $cooperativaId, $dbConfig, null, $user);

        return [
            'kpis' => $kpis,
            'chartData' => $chartData,
            'ranking' => $ranking,
            'cooperativas' => $this->getDashboardCooperativas($user),
            'filters' => $request->only(['cooperativa_id', 'fecha_desde', 'fecha_hasta']),
            'userRole' => $user->tipo_usuario,
        ];
    }

    private function calculateKpis(array $period, ?int $cooperativaId, array $config, User $user, bool $isHistorical = false): array
    {
        $baseQuery = Caso::query()
            ->when($cooperativaId, fn($q) => $q->where('cooperativa_id', $cooperativaId));

        $this->applyCaseAccessScope($baseQuery, $user);

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
                ->join('casos', $cfgCaso['table'].'.'.$cfgCaso['fk'], '=', 'casos.id')
                ->whereBetween($cfgCaso['table'].'.'.$cfgCaso['date'], [$period['start'], $period['end']])
                ->when($cooperativaId, fn($query) => $query->where('casos.cooperativa_id', $cooperativaId));

            $this->applyCaseAccessScopeToBuilder($q, $user, 'casos');
            $this->excludeDeletedCasesFromBuilder($q, 'casos');

            $totalRecuperado += $q->sum($cfgCaso['table'].'.'.$cfgCaso['amount']);
        }

        $cfgContrato = $config['contrato'];
        if (Schema::hasTable($cfgContrato['table']) && Schema::hasTable('contratos')) {
            $q = DB::table($cfgContrato['table'])
                ->whereBetween($cfgContrato['table'].'.'.$cfgContrato['date'], [$period['start'], $period['end']]);

            if ($cooperativaId || $user->tipo_usuario !== 'admin') {
                $q->join('contratos', $cfgContrato['table'].'.contrato_id', '=', 'contratos.id')
                  ->join('casos', 'contratos.caso_id', '=', 'casos.id')
                  ->when($cooperativaId, fn($query) => $query->where('casos.cooperativa_id', $cooperativaId));

                $this->applyCaseAccessScopeToBuilder($q, $user, 'casos');
                $this->excludeDeletedCasesFromBuilder($q, 'casos');
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

    private function getUnifiedChartData(array $period, ?int $cooperativaId, array $config, User $user): array
    {
        $baseQuery = Caso::query()
            ->when($cooperativaId, fn($q) => $q->where('cooperativa_id', $cooperativaId));

        $this->applyCaseAccessScope($baseQuery, $user);

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

        $isPgsql = DB::getDriverName() === 'pgsql';
        $oneYearAgo = Carbon::now()->subMonths(11)->startOfMonth();

        $qCasos = DB::table('pagos_caso')
            ->join('casos', 'pagos_caso.caso_id', '=', 'casos.id')
            ->select(
                DB::raw($isPgsql ? "TO_CHAR(pagos_caso.fecha_pago, 'YYYY-MM') as mes" : "DATE_FORMAT(pagos_caso.fecha_pago, '%Y-%m') as mes"),
                DB::raw('SUM(pagos_caso.monto_pagado) as total')
            )
            ->where('pagos_caso.fecha_pago', '>=', $oneYearAgo)
            ->when($cooperativaId, fn($q) => $q->where('casos.cooperativa_id', $cooperativaId));

        $this->applyCaseAccessScopeToBuilder($qCasos, $user, 'casos');
        $this->excludeDeletedCasesFromBuilder($qCasos, 'casos');

        $dataCasos = $qCasos->groupBy('mes')->pluck('total', 'mes');

        $qContratos = DB::table('contrato_pagos')
            ->select(
                DB::raw($isPgsql ? "TO_CHAR(contrato_pagos.fecha, 'YYYY-MM') as mes" : "DATE_FORMAT(contrato_pagos.fecha, '%Y-%m') as mes"),
                DB::raw('SUM(contrato_pagos.valor) as total')
            )
            ->where('contrato_pagos.fecha', '>=', $oneYearAgo);

        if ($cooperativaId || $user->tipo_usuario !== 'admin') {
            $qContratos->join('contratos', 'contrato_pagos.contrato_id', '=', 'contratos.id')
                ->join('casos', 'contratos.caso_id', '=', 'casos.id')
                ->when($cooperativaId, fn($q) => $q->where('casos.cooperativa_id', $cooperativaId));

            $this->applyCaseAccessScopeToBuilder($qContratos, $user, 'casos');
            $this->excludeDeletedCasesFromBuilder($qContratos, 'casos');
        }

        $dataContratos = $qContratos->groupBy('mes')->pluck('total', 'mes');

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

    private function getUnifiedRanking(array $period, ?int $cooperativaId, array $config, ?int $userId = null, ?User $viewer = null)
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
                ->when($cooperativaId, fn($q) => $q->where('casos.cooperativa_id', $cooperativaId));

            if ($viewer) {
                $this->applyCaseAccessScopeToBuilder($rankingCasos, $viewer, 'casos');
                $this->excludeDeletedCasesFromBuilder($rankingCasos, 'casos');
            }

            $rankingCasos = $rankingCasos
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
                ->when($cooperativaId, fn($q) => $q->where('casos.cooperativa_id', $cooperativaId));

            if ($viewer) {
                $this->applyCaseAccessScopeToBuilder($rankingContratos, $viewer, 'casos');
                $this->excludeDeletedCasesFromBuilder($rankingContratos, 'casos');
            }

            $rankingContratos = $rankingContratos
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
            ->take($userId ? 1 : 3)
            ->values()
            ->all();
    }

    private function requestedCooperativaId(Request $request): ?int
    {
        $value = $request->input('cooperativa_id');
        return filled($value) ? (int) $value : null;
    }

    private function getDashboardCooperativas(User $user)
    {
        if ($user->tipo_usuario === 'admin') {
            return Cooperativa::orderBy('nombre')->get(['id', 'nombre']);
        }

        if (!in_array($user->tipo_usuario, ['gestor', 'abogado'], true)) {
            return collect();
        }

        $ids = $this->userCooperativaIds($user);

        $directCaseCooperativaIds = Caso::query()
            ->whereNotNull('cooperativa_id')
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhereHas('users', fn($subQuery) => $subQuery->where('users.id', $user->id));
            })
            ->pluck('cooperativa_id');

        $ids = $ids->merge($directCaseCooperativaIds)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return Cooperativa::whereIn('id', $ids)->orderBy('nombre')->get(['id', 'nombre']);
    }

    private function userCooperativaIds(User $user)
    {
        return $user->cooperativas()->pluck('cooperativas.id');
    }

    private function applyCaseAccessScope($query, User $user): void
    {
        if ($user->tipo_usuario === 'admin') {
            return;
        }

        if (in_array($user->tipo_usuario, ['gestor', 'abogado'], true)) {
            $cooperativaIds = $this->userCooperativaIds($user);

            $query->where(function ($scope) use ($user, $cooperativaIds) {
                $scope->where('user_id', $user->id)
                    ->orWhereHas('users', fn($subQuery) => $subQuery->where('users.id', $user->id));

                if ($cooperativaIds->isNotEmpty()) {
                    $scope->orWhereIn('cooperativa_id', $cooperativaIds);
                }
            });

            return;
        }

        $query->whereRaw('1 = 0');
    }

    private function applyCaseAccessScopeToBuilder($query, User $user, string $caseTable = 'casos'): void
    {
        if ($user->tipo_usuario === 'admin') {
            return;
        }

        if (in_array($user->tipo_usuario, ['gestor', 'abogado'], true)) {
            $cooperativaIds = $this->userCooperativaIds($user);

            $query->where(function ($scope) use ($user, $cooperativaIds, $caseTable) {
                $scope->where("{$caseTable}.user_id", $user->id);

                if ($cooperativaIds->isNotEmpty()) {
                    $scope->orWhereIn("{$caseTable}.cooperativa_id", $cooperativaIds);
                }

                if (Schema::hasTable('caso_user')) {
                    $scope->orWhereExists(function ($subQuery) use ($user, $caseTable) {
                        $subQuery->select(DB::raw(1))
                            ->from('caso_user')
                            ->whereColumn('caso_user.caso_id', "{$caseTable}.id")
                            ->where('caso_user.user_id', $user->id);
                    });
                }
            });

            return;
        }

        $query->whereRaw('1 = 0');
    }

    private function excludeDeletedCasesFromBuilder($query, string $caseTable = 'casos'): void
    {
        if (Schema::hasColumn('casos', 'deleted_at')) {
            $query->whereNull("{$caseTable}.deleted_at");
        }
    }

    private function getClientDashboardData(User $user): array
    {
        $cfg = config('cartera', []);
        $T_PAGOS = $cfg['recoveries_table'] ?? 'pagos_caso';
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