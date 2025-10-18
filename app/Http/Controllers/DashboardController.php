<?php

namespace App\Http\Controllers;

use App\Models\Caso;
use App\Models\Cooperativa;
use App\Models\IncidenteJuridico;
use App\Models\User;
use App\Models\ValidacionLegal;
use App\Models\PagoCaso; // Importante tener el modelo de Pagos
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();
        
        if ($user->tipo_usuario === 'cliente' && $user->persona_id) {
            
            $casosClienteQuery = Caso::query()
                ->whereIn('estado_proceso', ['prejuridico', 'demandado'])
                ->where(function($q) use ($user) {
                    $q->where('deudor_id', $user->persona_id)
                      ->orWhere('codeudor1_id', $user->persona_id)
                      ->orWhere('codeudor2_id', $user->persona_id);
                });

            $casosActivosIds = (clone $casosClienteQuery)->pluck('id');
            $montoTotalDeuda = (clone $casosClienteQuery)->sum('monto_total');
            $totalPagado = PagoCaso::whereIn('caso_id', $casosActivosIds)->sum('monto_pagado');
            $saldoPendienteReal = $montoTotalDeuda - $totalPagado;

            $kpisCliente = [
                'saldo_total_pendiente' => $saldoPendienteReal,
                'casos_activos' => $casosActivosIds->count(),
            ];

            return Inertia::render('Dashboard/Index', [
                'kpis' => $kpisCliente,
                'userRole' => 'cliente'
            ]);
        }

        // --- LÓGICA PARA ADMIN, GESTOR, ABOGADO (VERSIÓN CORREGIDA) ---
        
        $baseQuery = Caso::query();

        $baseQuery->when($request->filled('cooperativa_id'), fn($q) => $q->where('cooperativa_id', $request->input('cooperativa_id')));
        $baseQuery->when($request->filled('fecha_desde'), fn($q) => $q->whereDate('fecha_apertura', '>=', $request->input('fecha_desde')));
        $baseQuery->when($request->filled('fecha_hasta'), fn($q) => $q->whereDate('fecha_apertura', '<=', $request->input('fecha_hasta')));
        
        // 1. Obtenemos los casos activos según los filtros
        $casosActivosQuery = (clone $baseQuery)->whereIn('estado_proceso', ['prejuridico', 'demandado']);
        $casosActivosIds = (clone $casosActivosQuery)->pluck('id');

        // 2. Calculamos la suma de las deudas de esos casos
        $montoTotalDeudasActivas = (clone $casosActivosQuery)->sum('monto_total');
        
        // 3. Calculamos la suma de los pagos para esos casos
        $totalPagadoActivo = PagoCaso::whereIn('caso_id', $casosActivosIds)->sum('monto_pagado');

        // 4. Calculamos el saldo pendiente real
        $saldoPendienteActivo = $montoTotalDeudasActivas - $totalPagadoActivo;

        $kpis = [
            'casos_activos' => $casosActivosIds->count(),
            'casos_demandados' => (clone $baseQuery)->where('estado_proceso', 'demandado')->count(),
            'mora_total' => $saldoPendienteActivo, // <-- ¡CORRECCIÓN APLICADA!
            'cumplimiento_legal' => $this->calcularCumplimiento($baseQuery),
        ];

        $chartData = [
        'casosPorEstado' => (clone $baseQuery)->select('estado_proceso', DB::raw('count(*) as total'))->groupBy('estado_proceso')->get()->pluck('total', 'estado_proceso'),
        'incidentesPorMes' => IncidenteJuridico::query()->select(DB::raw('TO_CHAR(fecha_registro, \'YYYY-MM\') as mes'), DB::raw('count(*) as total'))->where('fecha_registro', '>=', Carbon::now()->subYear())->groupBy('mes')->orderBy('mes')->get()->pluck('total', 'mes'),
        'validacionesPorEstado' => (clone $baseQuery)->join('validaciones_legales', 'casos.id', '=', 'validaciones_legales.caso_id')->select('validaciones_legales.estado', DB::raw('count(*) as total'))->groupBy('validaciones_legales.estado')->get()->pluck('total', 'estado'),
    ];
        
        $rankingQuery = User::query()->select('users.id', 'users.name', DB::raw('SUM(pagos_caso.monto_pagado) as total_recuperado'))->join('casos', 'users.id', '=', 'casos.user_id')->join('pagos_caso', 'casos.id', '=', 'pagos_caso.caso_id')->whereIn('users.tipo_usuario', ['abogado', 'gestor']);
        $rankingQuery->when($request->filled('fecha_desde'), fn($q) => $q->whereDate('pagos_caso.fecha_pago', '>=', $request->input('fecha_desde')));
        $rankingQuery->when($request->filled('fecha_hasta'), fn($q) => $q->whereDate('pagos_caso.fecha_pago', '<=', $request->input('fecha_hasta')));
        $rankingAbogados = $rankingQuery->groupBy('users.id', 'users.name')->orderByDesc('total_recuperado')->limit(3)->get();
                
        return Inertia::render('Dashboard/Index', [
            'kpis' => $kpis,
            'chartData' => $chartData,
            'rankingAbogados' => $rankingAbogados,
            'cooperativas' => Cooperativa::all(['id', 'nombre']),
            'filters' => $request->only(['cooperativa_id', 'fecha_desde', 'fecha_hasta']),
            'userRole' => $user->tipo_usuario,
        ]);
    }

    private function calcularCumplimiento($casoQuery)
    {
        $casoIds = (clone $casoQuery)->pluck('id');
        if ($casoIds->isEmpty()) return 100;
        $totalValidaciones = ValidacionLegal::whereIn('caso_id', $casoIds)->count();
        $validacionesCumplidas = ValidacionLegal::whereIn('caso_id', $casoIds)->where('estado', 'cumple')->count();
        return $totalValidaciones > 0 ? round(($validacionesCumplidas / $totalValidaciones) * 100, 1) : 100;
    }
}