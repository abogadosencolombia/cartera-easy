<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Http\Controllers\Controller;

class GestoresController extends Controller
{
    public function index(Request $request)
    {
        $cfg = config('cartera');

        // Tablas
        $T_USERS = $cfg['users_table'];
        $T_CASES = $cfg['cases_table'];
        $T_COOPS = $cfg['cooperativas_table'];
        $T_RECS  = $cfg['recoveries_table'];

        // PKs
        $PK_U = $cfg['user_pk'];
        $PK_C = $cfg['case_pk'];
        $PK_O = $cfg['cooperativa_pk'];

        // Detección de columnas
        $userNameCol = $this->pickColumn($T_USERS, $cfg['user_name_candidates'], 'name');
        $roleCol     = $cfg['role_column'] && Schema::hasColumn($T_USERS, $cfg['role_column'])
            ? $cfg['role_column'] : null;

        $caseUserFk  = $this->pickColumn($T_CASES, $cfg['case_user_fk_candidates']);
        $caseNumCol  = $this->pickColumn($T_CASES, $cfg['case_number_candidates'], $cfg['case_pk']);
        $caseCoopFk  = $this->pickColumn($T_CASES, $cfg['case_coop_fk_candidates']);

        $coopNameCol = $this->pickColumn($T_COOPS, $cfg['cooperativa_name_candidates'], $cfg['cooperativa_pk']);

        $recCaseFk   = $this->pickColumn($T_RECS, $cfg['recovery_case_fk_candidates']);
        $recAmtCol   = $this->pickColumn($T_RECS, $cfg['recovery_amount_candidates']);

        // Filtros
        $q    = trim((string) $request->get('q', ''));
        $sort = in_array($request->get('sort'), ['total_recovered','name']) ? $request->get('sort') : 'total_recovered';
        $dir  = strtolower($request->get('dir')) === 'asc' ? 'asc' : 'desc';

        // =================== INICIO DEL CÓDIGO REFACTORIZADO Y CORREGIDO ===================

        // Roles permitidos y prohibidos
        $allowedRoles   = array_values(array_unique(array_filter(array_merge(
            $cfg['role_values_for_agents'] ?? [],
            $cfg['role_values_for_admins'] ?? [],
            ['admin'] // asegura incluir admin si no está en config
        ))));
        $forbiddenRoles = array_values(array_unique(array_filter(array_merge(
            $cfg['role_values_forbidden'] ?? [],
            ['cliente'] // excluye cliente por defecto
        ))));

        // Modelo User para Spatie (evita mismatch 'App\User' vs 'App\Models\User')
        $userModelFqn = config('auth.providers.users.model', 'App\\Models\\User');

        // Base de usuarios-gestores
        $usersQuery = DB::table("{$T_USERS} as u")
            ->select("u.{$PK_U}", "u.{$userNameCol} as name");

        // Filtro de roles
        if ($roleCol) {
            // Columna de rol directa en users
            if (!empty($allowedRoles)) {
                $usersQuery->whereIn("u.{$roleCol}", $allowedRoles);
            }
            if (!empty($forbiddenRoles)) {
                $usersQuery->whereNotIn("u.{$roleCol}", $forbiddenRoles);
            }
        } elseif (Schema::hasTable('model_has_roles') && Schema::hasTable('roles')) {
            // Spatie Permission
            $usersQuery->whereExists(function ($query) use ($PK_U, $allowedRoles, $forbiddenRoles, $userModelFqn) {
                $query->select(DB::raw(1))
                    ->from('model_has_roles as mhr')
                    ->join('roles as r', 'r.id', '=', 'mhr.role_id')
                    ->whereColumn('mhr.model_id', '=', "u.{$PK_U}")
                    ->where('mhr.model_type', $userModelFqn);

                if (!empty($allowedRoles)) {
                    $query->whereIn('r.name', $allowedRoles);
                }
                if (!empty($forbiddenRoles)) {
                    $query->whereNotIn('r.name', $forbiddenRoles);
                }
            });
        } else {
            // Sin sistema de roles confiable: último recurso, intenta filtrar por candidatos de columna tipo_usuario
            if (Schema::hasColumn($T_USERS, 'tipo_usuario')) {
                if (!empty($allowedRoles)) {
                    $usersQuery->whereIn('u.tipo_usuario', $allowedRoles);
                }
                if (!empty($forbiddenRoles)) {
                    $usersQuery->whereNotIn('u.tipo_usuario', $forbiddenRoles);
                }
            } else {
                // No hay forma segura de distinguir. Evita mostrar clientes devolviendo vacío.
                return Inertia::render('Admin/Gestores/Index', [
                    'filters' => ['q' => $q, 'sort' => $sort, 'dir' => $dir],
                    'rows' => [],
                    'routesBase' => $cfg['frontend_paths'],
                ]);
            }
        }

        // Búsqueda por nombre
        if ($q !== '') {
            $usersQuery->where("u.{$userNameCol}", 'like', "%".$q."%");
        }

        $usersList = $usersQuery->get();
        $userIds = $usersList->pluck($PK_U)->all();

        // Si no hay gestores/abogados/admin, retornar vacío
        if (empty($userIds)) {
            return Inertia::render('Admin/Gestores/Index', [
                'filters' => ['q' => $q, 'sort' => $sort, 'dir' => $dir],
                'rows' => [],
                'routesBase' => $cfg['frontend_paths'],
            ]);
        }

        // =================== FIN DEL CÓDIGO REFACTORIZADO Y CORREGIDO ===================

        // Subconsulta: recuperado por caso
        $recoveredPerCase = DB::table($T_RECS)
            ->select([$recCaseFk.' as case_id', DB::raw("COALESCE(SUM($recAmtCol),0) as recovered")])
            ->groupBy($recCaseFk);

        // Agregado por usuario
        $totalsByUser = DB::table($T_CASES.' as c')
            ->leftJoinSub($recoveredPerCase, 'r', function($j) use ($PK_C){
                $j->on('r.case_id','=','c.'.$PK_C);
            })
            ->whereIn('c.'.$caseUserFk, $userIds)
            ->groupBy('c.'.$caseUserFk)
            ->select([
                'c.'.$caseUserFk.' as user_id',
                DB::raw('COALESCE(SUM(r.recovered),0) as total_recovered'),
                DB::raw('COUNT(*) as casos_count')
            ])->get()->keyBy('user_id');

        // Detalle de casos por usuario con cooperativa y recuperado por caso
        $casesByUser = DB::table($T_CASES.' as c')
            ->leftJoin($T_COOPS.' as o', 'o.'.$PK_O, '=', 'c.'.$caseCoopFk)
            ->leftJoinSub($recoveredPerCase, 'r', function($j) use ($PK_C){
                $j->on('r.case_id','=','c.'.$PK_C);
            })
            ->whereIn('c.'.$caseUserFk, $userIds)
            ->orderBy('c.'.$PK_C)
            ->select([
                'c.'.$caseUserFk.' as user_id',
                'c.'.$PK_C.' as case_id',
                'c.'.$caseNumCol.' as case_number',
                'o.'.$PK_O.' as coop_id',
                'o.'.$coopNameCol.' as coop_name',
                DB::raw('COALESCE(r.recovered,0) as recovered')
            ])->get()->groupBy('user_id');

        // Construcción final
        $rows = [];
        foreach ($usersList as $u) {
            $total = $totalsByUser[$u->$PK_U]->total_recovered ?? 0;
            $userCases = $casesByUser[$u->$PK_U] ?? collect();

            $coopsUnique = $userCases->pluck('coop_id','coop_id')->keys()->filter()->values();
            $coopsNamed  = $userCases->whereNotNull('coop_id')->map(function($x){
                return ['id'=>$x->coop_id, 'name'=>$x->coop_name];
            })->unique('id')->values()->all();

            $rows[] = [
                'id' => $u->$PK_U,
                'name' => $u->name,
                'total_recovered' => (float) $total,
                'casos_count' => (int) ($totalsByUser[$u->$PK_U]->casos_count ?? 0),
                'cooperativas_count' => count($coopsUnique),
                'cases' => $userCases->map(function($x){
                    return [
                        'id' => $x->case_id,
                        'number' => $x->case_number,
                        'recovered' => (float) $x->recovered,
                        'cooperativa' => $x->coop_id ? ['id'=>$x->coop_id,'name'=>$x->coop_name] : null,
                    ];
                })->values()->all(),
                'cooperativas' => $coopsNamed,
            ];
        }

        // Ordenación en servidor
        if ($sort === 'name') {
            usort($rows, fn($a,$b) => $dir==='asc' ? strcasecmp($a['name'],$b['name']) : strcasecmp($b['name'],$a['name']));
        } else {
            usort($rows, fn($a,$b) => $dir==='asc' ? $a['total_recovered']<=>$b['total_recovered'] : $b['total_recovered']<=>$a['total_recovered']);
        }

        return Inertia::render('Admin/Gestores/Index', [
            'filters' => [
                'q' => $q,
                'sort' => $sort,
                'dir' => $dir,
            ],
            'rows' => array_values($rows),
            'routesBase' => $cfg['frontend_paths'],
        ]);
    }

    private function pickColumn(string $table, array $candidates, ?string $fallback = null): string
    {
        foreach ($candidates as $col) {
            if (Schema::hasColumn($table, $col)) return $col;
        }
        if ($fallback) return $fallback;
        abort(500, "No se encontró columna adecuada en {$table}");
    }
}

