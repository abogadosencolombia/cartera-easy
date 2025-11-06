<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Caso;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GestoresController extends Controller
{
    public function index(Request $request)
    {
        // 1. Leemos tu configuración inteligente
        $cfg = config('cartera', []);
        $T_RECS = $cfg['recoveries_table'] ?? 'pagos_casos';
        $recAmtCol = $this->pickColumn($T_RECS, $cfg['recovery_amount_candidates'] ?? ['monto_recuperado'], 'monto_recuperado');
        $recUserFk = $this->pickColumn($T_RECS, $cfg['recovery_user_fk_candidates'] ?? ['user_id'], 'user_id');

        // 2. Validar filtros de entrada
        $request->validate([
            'q' => 'nullable|string|max:100',
            'sort' => 'nullable|string|in:total_recovered,name,casos_count',
            'dir' => 'nullable|string|in:asc,desc',
        ]);

        // 3. Construir la consulta base con Eloquent y filtro de roles
        $query = User::query()
            ->whereIn('tipo_usuario', ['admin', 'gestor', 'abogado'])
            ->select('id', 'name', 'email', 'tipo_usuario')
            ->addSelect(DB::raw("(SELECT SUM({$recAmtCol}) FROM {$T_RECS} WHERE {$recUserFk} = users.id) as total_recovered"))
            ->addSelect(DB::raw(
                "(SELECT COUNT(*) FROM casos WHERE casos.user_id = users.id OR casos.user_id = users.id) as casos_count"
        ));

        // 4. Aplicar filtro de búsqueda por nombre
        if ($request->filled('q')) {
            $query->where('name', 'ilike', '%' . $request->q . '%');
        }

        // 5. Calcular KPIs Totales
        $kpiQuery = $query->clone();
        $allMatchingUsers = $kpiQuery->get();
        $totalCasosReales = DB::table('casos')->count();
        $totals = [
            'totalRecovered' => $allMatchingUsers->sum('total_recovered'),
            'totalCasos' => $totalCasosReales, // <-- ¡Usamos el conteo real!
            'totalUsers' => $allMatchingUsers->count(),
        ];

        // 6. Aplicar ordenamiento en la base de datos
        $sortField = $request->input('sort', 'total_recovered');
        $sortDirection = $request->input('dir', 'desc');
        $query->orderBy($sortField, $sortDirection);

        // 7. Paginar los resultados para un rendimiento óptimo
        $rows = $query->paginate(10)->withQueryString();

        // 8. Cargar y transformar los datos explícitamente para el frontend
        // 8. Cargar y transformar los datos explícitamente para el frontend
$finalRows = $rows->through(function ($user) {
    // Cargar cooperativas (como ya estaba)
    $user->load('cooperativas:id,nombre');
    
    // --- INICIO DE LÓGICA CORREGIDA ---
    
    // 1. Cargar TODOS los casos asignados a este usuario (sea gestor o abogado)
    // He reemplazado 'nombre_caso' y 'radicado' con las columnas de tus screenshots:
    $todosLosCasos = Caso::where('user_id', $user->id)
                            // ↓↓↓↓ ESTAS SON LAS COLUMNAS REALES DE TU TABLA ↓↓↓↓
                            ->select('id', 'referencia_credito', 'tipo_proceso') 
                            ->get();
    
    // --- FIN DE LÓGICA CORREGIDA ---

    return [
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'tipo_usuario' => $user->tipo_usuario,
        'total_recovered' => (float) $user->total_recovered,
        'casos_count' => $user->casos_count, // Este valor viene de tu Paso 3 y está BIEN
        'cooperativas_count' => $user->cooperativas->count(),
        'cooperativas' => $user->cooperativas->map(fn($coop) => [
            'id' => $coop->id,
            'nombre' => $coop->nombre,
        ]),
        
        // --- ¡AQUÍ ESTÁ LA MAGIA! ---
        // Forzamos la inclusión de la lista detallada de casos.
        'casos' => $todosLosCasos->map(fn($caso) => [
            'id' => $caso->id,
             // ↓↓↓↓ He cambiado los nombres para que tu frontend los reciba ↓↓↓↓
            'referencia' => $caso->referencia_credito, 
            'proceso' => $caso->tipo_proceso,
        ]),
    ];
});

        // 9. Devolver todo a la vista de Inertia
        return Inertia::render('Admin/Gestores/Index', [
            'rows' => $finalRows,
            'filters' => $request->only('q', 'sort', 'dir'),
            'totals' => $totals,
        ]);
    }

    /**
     * Función de ayuda para detectar la columna correcta.
     */
    private function pickColumn(string $table, array $candidates, ?string $fallback = null): string
    {
        foreach ($candidates as $col) {
            if (Schema::hasColumn($table, $col)) {
                return $col;
            }
        }
        if ($fallback) {
            return $fallback;
        }
        abort(500, "No se encontró una columna de base de datos adecuada en la tabla '{$table}' para la funcionalidad de reportes.");
    }
}

