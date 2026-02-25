<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Caso;
use App\Models\ProcesoRadicado; 
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GestoresController extends Controller
{
    public function index(Request $request)
    {
        // 1. Configuración de Tablas
        $T_PAGOS = 'contrato_pagos'; 
        $T_CONTRATOS = 'contratos';
        $T_CASOS = 'casos';
        $T_PROCESOS = 'proceso_radicados';
        
        if (!Schema::hasTable($T_PROCESOS)) {
            $T_PROCESOS = Schema::hasTable('procesos') ? 'procesos' : 'proceso_radicados';
        }

        $colMonto = 'valor';

        // 2. Validar
        $request->validate([
            'q' => 'nullable|string|max:100',
            'sort' => 'nullable|string|in:total_recovered,name,casos_count',
            'dir' => 'nullable|string|in:asc,desc',
        ]);

        // 3. Consulta Base para la LISTA DE USUARIOS
        $query = User::query()
            ->whereIn('tipo_usuario', ['admin', 'gestor', 'abogado'])
            ->select('users.id', 'users.name', 'users.email', 'users.tipo_usuario');

        // --- CÁLCULO DE RECUPERACIÓN INDIVIDUAL ---
        $fkProceso = 'proceso_radicado_id';
        if (!Schema::hasColumn($T_CONTRATOS, $fkProceso) && Schema::hasColumn($T_CONTRATOS, 'proceso_id')) {
            $fkProceso = 'proceso_id';
        }

        $subqueryRecuperadoIndividual = "
            (SELECT COALESCE(SUM($T_PAGOS.$colMonto), 0)
             FROM $T_PAGOS
             JOIN $T_CONTRATOS ON $T_PAGOS.contrato_id = $T_CONTRATOS.id
             LEFT JOIN $T_CASOS ON $T_CONTRATOS.caso_id = $T_CASOS.id
             LEFT JOIN $T_PROCESOS ON $T_CONTRATOS.$fkProceso = $T_PROCESOS.id
             WHERE 
                ($T_CASOS.user_id = users.id AND $T_CASOS.deleted_at IS NULL)
                OR
                ($T_PROCESOS.abogado_id = users.id)
                OR
                ($T_PROCESOS.created_by = users.id)
             )
        ";

        $query->addSelect(DB::raw("$subqueryRecuperadoIndividual as total_recovered"));

        // --- CONTEO DE CASOS ---
        $subqueryConteo = "
            (
                (SELECT COUNT(*) FROM $T_CASOS WHERE $T_CASOS.user_id = users.id AND $T_CASOS.deleted_at IS NULL)
                +
                (SELECT COUNT(*) FROM $T_PROCESOS WHERE ($T_PROCESOS.abogado_id = users.id OR $T_PROCESOS.created_by = users.id))
            )
        ";
        $query->addSelect(DB::raw("$subqueryConteo as casos_count"));

        // 4. Filtro
        if ($request->filled('q')) {
            $query->where('name', 'ilike', '%' . $request->q . '%');
        }

        // 5. CÁLCULO DE TOTALES REALES
        $userIds = $query->pluck('id');

        $totalRecuperadoReal = DB::table($T_PAGOS)
            ->join($T_CONTRATOS, "$T_PAGOS.contrato_id", '=', "$T_CONTRATOS.id")
            ->leftJoin($T_CASOS, "$T_CONTRATOS.caso_id", '=', "$T_CASOS.id")
            ->leftJoin($T_PROCESOS, "$T_CONTRATOS.$fkProceso", '=', "$T_PROCESOS.id")
            ->where(function($q) use ($userIds, $T_CASOS, $T_PROCESOS) {
                $q->whereIn("$T_CASOS.user_id", $userIds)
                  ->orWhereIn("$T_PROCESOS.abogado_id", $userIds)
                  ->orWhereIn("$T_PROCESOS.created_by", $userIds);
            })
            // CORRECCIÓN AQUÍ: Agregamos $T_PROCESOS al use()
            ->where(function($q) use ($T_CASOS, $T_PROCESOS) {
                 $q->whereNull("$T_CASOS.deleted_at")
                   ->orWhereNotNull("$T_PROCESOS.id"); 
            })
            ->sum("$T_PAGOS.$colMonto");

        $totals = [
            'totalRecovered' => $totalRecuperadoReal, 
            'totalCasos' => DB::table($T_CASOS)->whereNull('deleted_at')->count() + DB::table($T_PROCESOS)->count(),
            'totalUsers' => $userIds->count(),
        ];

        // 6. Orden
        $sortField = $request->input('sort', 'total_recovered');
        $sortDirection = $request->input('dir', 'desc');
        $query->orderBy($sortField, $sortDirection);

        // 7. Paginación
        $rows = $query->paginate(10)->withQueryString();

        // 8. Transformación
        $finalRows = $rows->through(function ($user) {
            $user->load('cooperativas:id,nombre');
            
            // Cargar Casos
            $misCasos = Caso::where('user_id', $user->id)
                            ->select('id', 'referencia_credito', 'tipo_proceso') 
                            ->limit(25) 
                            ->get()
                            ->toBase()
                            ->map(fn($c) => [
                                'id' => $c->id,
                                'tipo' => 'Caso',
                                'referencia' => $c->referencia_credito ?? "Caso #{$c->id}",
                                'proceso' => $c->tipo_proceso ?? 'General',
                                'link' => route('casos.show', $c->id)
                            ]);

            // Cargar Procesos
            try {
                $misProcesos = \App\Models\ProcesoRadicado::where(function($q) use ($user) {
                                    $q->where('abogado_id', $user->id)
                                      ->orWhere('created_by', $user->id);
                                })
                                ->select('id', 'radicado', 'asunto')
                                ->limit(25)
                                ->get()
                                ->toBase()
                                ->map(fn($p) => [
                                    'id' => $p->id,
                                    'tipo' => 'Proceso',
                                    'referencia' => $p->radicado ?? "Rad #{$p->id}",
                                    'proceso' => $p->asunto ?? 'Jurídico',
                                    'link' => route('procesos.show', $p->id)
                                ]);
            } catch (\Exception $e) {
                $misProcesos = collect([]);
            }

            $listaCombinada = $misCasos->merge($misProcesos)->take(50);

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'tipo_usuario' => $user->tipo_usuario,
                'total_recovered' => (float) $user->total_recovered,
                'casos_count' => $user->casos_count,
                'cooperativas_count' => $user->cooperativas->count(),
                'cooperativas' => $user->cooperativas->map(fn($c) => ['id' => $c->id, 'nombre' => $c->nombre]),
                'casos' => $listaCombinada,
            ];
        });

        return Inertia::render('Admin/Gestores/Index', [
            'rows' => $finalRows,
            'filters' => $request->only('q', 'sort', 'dir'),
            'totals' => $totals,
        ]);
    }
}