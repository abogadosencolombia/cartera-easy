<?php

namespace App\Http\Controllers;

use App\Models\Caso;
use App\Models\Cooperativa;
use App\Models\IncidenteJuridico;
use App\Models\User;
use App\Models\ValidacionLegal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * Muestra el dashboard apropiado según el rol del usuario.
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();

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
                'serverError' => 'Ocurrió un error al cargar los datos del dashboard. El equipo técnico ha sido notificado.'
            ]);
        }

        return Inertia::render('Dashboard/Index', $props);
    }

    /**
     * Prepara los datos para el dashboard de Administradores, Gestores y Abogados.
     */
    private function getAdminDashboardData(User $user, Request $request): array
    {
        $cfg = config('cartera', []);
        $T_PAGOS = $cfg['recoveries_table'] ?? 'pago_casos';
        $Col_MONTO_PAGO = $this->pickColumn($T_PAGOS, $cfg['recovery_amount_candidates'] ?? [], 'monto_pagado');
        $Col_FECHA_PAGO = $this->pickColumn($T_PAGOS, $cfg['recovery_date_candidates'] ?? [], 'fecha_pago');
        $Fk_CASO_EN_PAGO = $this->pickColumn($T_PAGOS, $cfg['recovery_case_fk_candidates'] ?? [], 'caso_id');

        $currentPeriod = $this->getPeriodFromRequest($request, 'current');
        $previousPeriod = $this->getPeriodFromRequest($request, 'previous');

        $currentKpis = $this->calculateAdminKpisForPeriod($currentPeriod, $request->input('cooperativa_id'), $T_PAGOS, $Fk_CASO_EN_PAGO, $Col_MONTO_PAGO);
        $previousKpis = $this->calculateAdminKpisForPeriod($previousPeriod, $request->input('cooperativa_id'), $T_PAGOS, $Fk_CASO_EN_PAGO, $Col_MONTO_PAGO);
        
        $kpis = [
            'saldo_total_activo' => $this->formatKpiWithTrend($currentKpis['saldo_total_activo'], $previousKpis['saldo_total_activo']),
            'tasa_recuperacion'  => $this->formatKpiWithTrend($currentKpis['tasa_recuperacion'], $previousKpis['tasa_recuperacion']),
            'casos_activos'      => $this->formatKpiWithTrend($currentKpis['casos_activos'], $previousKpis['casos_activos']),
            'casos_cerrados'     => $this->formatKpiWithTrend($currentKpis['casos_cerrados'], $previousKpis['casos_cerrados']),
        ];

        $chartData = $this->getChartData($currentPeriod, $request->input('cooperativa_id'), $T_PAGOS, $Fk_CASO_EN_PAGO, $Col_MONTO_PAGO, $Col_FECHA_PAGO);
        
        $ranking = User::query()
            ->select('users.id', 'users.name', DB::raw("SUM({$T_PAGOS}.{$Col_MONTO_PAGO}) as total_recuperado"))
            ->join('casos', 'users.id', '=', 'casos.user_id')
            ->join($T_PAGOS, 'casos.id', '=', "{$T_PAGOS}.{$Fk_CASO_EN_PAGO}")
            ->whereIn('users.tipo_usuario', ['abogado', 'gestor', 'admin'])
            ->whereBetween("{$T_PAGOS}.{$Col_FECHA_PAGO}", [$currentPeriod['start'], $currentPeriod['end']])
            ->when($request->filled('cooperativa_id'), fn ($q) => $q->where('casos.cooperativa_id', $request->input('cooperativa_id')))
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total_recuperado')
            ->limit(3)
            ->get();
            
        return [
            'kpis' => $kpis,
            'chartData' => $chartData,
            'ranking' => $ranking,
            'cooperativas' => Cooperativa::orderBy('nombre')->get(['id', 'nombre']),
            'filters' => $request->only(['cooperativa_id', 'fecha_desde', 'fecha_hasta']),
            'userRole' => $user->tipo_usuario,
        ];
    }

    private function getClientDashboardData(User $user): array
    {
        $cfg = config('cartera', []);
        $T_PAGOS = $cfg['recoveries_table'] ?? 'pago_casos';
        $Col_MONTO_PAGO = $this->pickColumn($T_PAGOS, $cfg['recovery_amount_candidates'] ?? [], 'monto_pagado');
        $Fk_CASO_EN_PAGO = $this->pickColumn($T_PAGOS, $cfg['recovery_case_fk_candidates'] ?? [], 'caso_id');

        $casosQuery = Caso::query()->where(fn ($q) => 
            $q->where('deudor_id', $user->persona_id)
              ->orWhere('codeudor1_id', $user->persona_id)
              ->orWhere('codeudor2_id', $user->persona_id)
        );

        // --- CAMBIO AQUÍ TAMBIÉN ---
        // Se considera activo cualquier caso que no esté 'cerrado' para el cliente.
        $casosActivosQuery = (clone $casosQuery)->where('estado_proceso', '!=', 'cerrado');
        $casosActivosIds = (clone $casosActivosQuery)->pluck('id');
        $montoTotalDeuda = (clone $casosActivosQuery)->sum('monto_total');
        
        $totalPagado = $casosActivosIds->isEmpty() ? 0 : DB::table($T_PAGOS)->whereIn($Fk_CASO_EN_PAGO, $casosActivosIds)->sum($Col_MONTO_PAGO);
        
        return [
            'kpis' => [
                'saldo_total_pendiente' => $montoTotalDeuda - $totalPagado,
                'casos_activos' => $casosActivosIds->count(),
            ],
            'userRole' => 'cliente',
        ];
    }

    private function calculateAdminKpisForPeriod(array $period, ?int $cooperativaId, string $tablaPagos, string $fkCasoEnPago, string $colMontoPago): array
    {
        $baseQuery = Caso::query()
            ->whereBetween('fecha_apertura', [$period['start'], $period['end']])
            ->when($cooperativaId, fn($q) => $q->where('cooperativa_id', $cooperativaId));
        
        // --- ¡LA CORRECCIÓN PRINCIPAL ESTÁ AQUÍ! ---
        // Ahora, "casos activos" incluye CUALQUIER estado que NO SEA 'cerrado'.
        // Si tienes otros estados de finalización como 'finalizado' o 'archivado', añádelos aquí.
        $casosActivosQuery = (clone $baseQuery)->whereNotIn('estado_proceso', ['cerrado']);
        
        $casosActivosIds = $casosActivosQuery->pluck('id');
        $casosCerradosCount = (clone $baseQuery)->where('estado_proceso', 'cerrado')->count();

        if ($casosActivosIds->isEmpty()) {
             return [
                'casos_activos' => 0,
                'saldo_total_activo' => 0,
                'tasa_recuperacion' => 0,
                'casos_cerrados' => $casosCerradosCount,
            ];
        }

        $montoTotalDeudas = (clone $casosActivosQuery)->sum('monto_total');
        $totalPagado = DB::table($tablaPagos)->whereIn($fkCasoEnPago, $casosActivosIds)->sum($colMontoPago);
        $tasaRecuperacion = ($montoTotalDeudas > 0) ? round(($totalPagado / $montoTotalDeudas) * 100, 1) : 0;

        return [
            'casos_activos' => $casosActivosIds->count(),
            'saldo_total_activo' => $montoTotalDeudas - $totalPagado,
            'tasa_recuperacion' => $tasaRecuperacion,
            'casos_cerrados' => $casosCerradosCount,
        ];
    }

    private function getChartData(array $period, ?int $cooperativaId, string $tablaPagos, string $fkCasoEnPago, string $colMontoPago, string $colFechaPago): array
    {
        $baseQuery = Caso::query()
            ->whereBetween('fecha_apertura', [$period['start'], $period['end']])
            ->when($cooperativaId, fn($q) => $q->where('cooperativa_id', $cooperativaId));

        return [
            'casosPorEstado' => (clone $baseQuery)
                ->select('estado_proceso', DB::raw('count(*) as total'))
                ->groupBy('estado_proceso')->get()->pluck('total', 'estado_proceso'),
            'incidentesPorMes' => IncidenteJuridico::query()
                ->select(DB::raw("TO_CHAR(fecha_registro, 'YYYY-MM') as mes"), DB::raw('count(*) as total'))
                ->where('fecha_registro', '>=', Carbon::now()->subYear())
                ->groupBy('mes')->orderBy('mes')->get()->pluck('total', 'mes'),
            'recuperacionPorMes' => Caso::query()
                ->join($tablaPagos, 'casos.id', '=', "{$tablaPagos}.{$fkCasoEnPago}")
                ->select(DB::raw("TO_CHAR({$tablaPagos}.{$colFechaPago}, 'YYYY-MM') as mes"), DB::raw("SUM({$tablaPagos}.{$colMontoPago}) as total"))
                ->where("{$tablaPagos}.{$colFechaPago}", '>=', Carbon::now()->subYear())
                ->when($cooperativaId, fn($q) => $q->where('casos.cooperativa_id', $cooperativaId))
                ->groupBy('mes')->orderBy('mes')->get()->pluck('total', 'mes'),
        ];
    }

    private function getPeriodFromRequest(Request $request, string $type): array
    {
        $from = $request->filled('fecha_desde') ? Carbon::parse($request->input('fecha_desde')) : Carbon::now()->subDays(29);
        $to = $request->filled('fecha_hasta') ? Carbon::parse($request->input('fecha_hasta')) : Carbon::now();

        if ($type === 'current') {
            return ['start' => $from->copy()->startOfDay(), 'end' => $to->copy()->endOfDay()];
        }
        
        $durationInDays = $to->diffInDays($from);
        $prevEnd = $from->copy()->subDay();
        $prevStart = $prevEnd->copy()->subDays($durationInDays);
        return ['start' => $prevStart->startOfDay(), 'end' => $prevEnd->endOfDay()];
    }

    private function formatKpiWithTrend($currentValue, $previousValue): array
    {
        if (is_null($previousValue) || $previousValue == 0) {
            $trend = $currentValue > 0 ? 100.0 : 0.0;
        } else {
            $trend = round((($currentValue - $previousValue) / abs($previousValue)) * 100, 1);
        }

        return ['value' => $currentValue, 'trend' => $trend, 'direction' => $trend >= 0 ? 'up' : 'down'];
    }
    
    private function pickColumn(string $table, array $candidates, ?string $fallback = null): string
    {
        if (!Schema::hasTable($table)) {
            return $fallback ?? $candidates[0] ?? 'id';
        }
        foreach ($candidates as $col) {
            if (Schema::hasColumn($table, $col)) return $col;
        }
        if ($fallback) return $fallback;
        
        return $candidates[0] ?? $fallback ?? 'id';
    }
}

