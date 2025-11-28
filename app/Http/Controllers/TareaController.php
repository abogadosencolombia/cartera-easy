<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Models\Tarea;
use App\Models\User;
use App\Models\Caso;
use App\Models\Contrato;
use App\Models\ProcesoRadicado;
use App\Models\Persona;
use App\Notifications\NuevaTareaAsignada;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification; // Importante
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TareaController extends Controller
{
    use AuthorizesRequests;

    /**
     * Muestra el panel de administración de tareas.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Tarea::class); 

        $query = Tarea::with(['asignadoA:id,name', 'creadaPor:id,name', 'tarea']);

        if ($request->filled('estado') && $request->estado !== 'todos') {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('user_id') && $request->user_id !== 'todos') {
            $query->where('user_id', $request->user_id);
        }

        $tareas = $query->latest('created_at')->paginate(20)->withQueryString();

        $usuariosAsignables = User::whereIn('tipo_usuario', ['abogado', 'gestor', 'admin'])
                                        ->orderBy('name')
                                        ->get(['id', 'name']);

        return Inertia::render('Admin/Tareas/Index', [
            'tareas' => $tareas,
            'usuarios' => $usuariosAsignables,
            'filtros' => $request->only(['estado', 'user_id'])
        ]);
    }

    /**
     * Guarda una nueva tarea y notifica al usuario.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Tarea::class);

        $validated = $request->validate([
            'titulo' => 'required|string|max:255',
            'descripcion' => 'required|string|max:5000',
            'user_id' => 'required|exists:users,id',
            'tarea_type' => 'required|string', // Se recibe 'proceso', 'caso', 'contrato'
            'tarea_id' => 'required|integer',
        ]);

        $modelMap = [
            'proceso' => ProcesoRadicado::class,
            'caso' => Caso::class,
            'contrato' => Contrato::class,
        ];

        // El 'tarea_type' viene del frontend como string ('proceso', 'caso', 'contrato')
        $modelClass = $modelMap[$validated['tarea_type']] ?? null;

        if (!$modelClass || !class_exists($modelClass)) {
            return back()->withErrors(['tarea_type' => 'El tipo de elemento vinculado no es válido.']);
        }

        $elemento = $modelClass::find($validated['tarea_id']);
        if (!$elemento) {
            return back()->withErrors(['tarea_id' => 'El elemento vinculado no existe.']);
        }

        $tarea = Tarea::create([
            'titulo' => $validated['titulo'],
            'descripcion' => $validated['descripcion'],
            'user_id' => $validated['user_id'],
            'admin_id' => Auth::id(),
            'tarea_type' => $modelClass, // Aquí se guarda la clase del Modelo
            'tarea_id' => $validated['tarea_id'],
            'estado' => 'pendiente',
        ]);

        $usuarioAsignado = User::find($validated['user_id']);
        if ($usuarioAsignado) {
            $usuarioAsignado->notify(new NuevaTareaAsignada($tarea));
        }

        return to_route('admin.tareas.index')->with('success', 'Tarea asignada y notificada correctamente.');
    }

    /**
     * Marca una tarea como completada.
     */
    public function marcarComoCompletada(Request $request, Tarea $tarea)
    {
        // BUG CORREGIDO: La autorización debe ser contra el 'user_id' de la tarea, no del admin
        if (Auth::id() !== $tarea->user_id && !Auth::user()->hasRole('admin')) {
             abort(403, 'No estás autorizado para completar esta tarea.');
        }

        if ($tarea->estado === 'pendiente') {
            $tarea->update([
                'estado' => 'completada',
                'fecha_completado' => Carbon::now()
            ]);
            
            return back(303)->with('success', 'Tarea marcada como completada.');
        }

        return back(303)->with('info', 'Esta tarea ya estaba completada.');
    }

    /**
     * Elimina una tarea.
     */
    public function destroy(Tarea $tarea)
    {
        $this->authorize('delete', $tarea);
        
        // --- INICIO DE LA MODIFICACIÓN (LA SOLUCIÓN A PRUEBA DE FALLOS) ---
        
        // Ya que la columna 'data' es TIPO TEXTO, no podemos usar operadores JSON.
        // En su lugar, buscamos el string literal "tarea_id":ID dentro del texto.
        // Ej: ... "link":"/casos/1", "tarea_id":7 }
        $searchString = '"tarea_id":' . $tarea->id;

        DatabaseNotification::where('type', \App\Notifications\NuevaTareaAsignada::class)
            ->where('data', 'LIKE', '%' . $searchString . '%')
            ->delete();
        
        // --- FIN DE LA MODIFICACIÓN ---

        // Ahora sí, eliminar la tarea
        $tarea->delete();
        
        // Devolver respuesta
        return to_route('admin.tareas.index')->with('success', 'Tarea y notificación eliminadas.');
    }

    // --- INICIO: MÉTODOS DE BÚSQUEDA CORREGIDOS Y SEPARADOS ---

    /**
     * Busca Procesos (Radicados)
     */
    public function buscarProcesos(Request $request)
    {
        $queryTerm = $request->q;
        // CORRECCIÓN: Usar LOWER() y LIKE para compatibilidad con todas las BD
        $queryLike = "%" . strtolower($queryTerm) . "%"; 
        $baseQuery = ProcesoRadicado::with('demandante:id,nombre_completo');

        if (!$queryTerm) {
            $resultados = $baseQuery->latest()->limit(10)->get();
        } else {
            $resultados = $baseQuery
                ->where(function ($q) use ($queryLike) { 
                    $q->whereRaw('LOWER(radicado) LIKE ?', [$queryLike])
                      ->orWhereRaw('LOWER(asunto) LIKE ?', [$queryLike])
                      ->orWhereHas('demandante', function ($subQ) use ($queryLike) {
                            $subQ->whereRaw('LOWER(nombre_completo) LIKE ?', [$queryLike]);
                      });
                })
                ->limit(10)->get();
        }
        return response()->json($resultados->map(fn ($p) => [
            'id' => $p->id, 
            'texto' => "Rad: {$p->radicado}" . ($p->demandante ? " - {$p->demandante->nombre_completo}" : "")
        ]));
    }

    /**
     * Busca Casos
     */
    public function buscarCasos(Request $request)
    {
        $queryTerm = $request->q;
        // CORRECCIÓN: Usar LOWER() y LIKE
        $queryLike = "%" . strtolower($queryTerm) . "%";
        $baseQuery = Caso::with('deudor:id,nombre_completo');

        if (!$queryTerm) {
            $resultados = $baseQuery->latest()->limit(10)->get();
        } else {
            $resultados = $baseQuery
                ->where(function ($q) use ($queryLike) { 
                       $q->whereRaw('LOWER(numero_caso) LIKE ?', [$queryLike])
                         // CORRECCIÓN: Usar LIKE estándar para el CAST
                         ->orWhereRaw('CAST(id AS TEXT) LIKE ?', [$queryLike]) 
                         ->orWhereHas('deudor', function ($subQ) use ($queryLike) { 
                            $subQ->whereRaw('LOWER(nombre_completo) LIKE ?', [$queryLike]);
                         });
                }) 
                ->limit(10)->get();
        }
        return response()->json($resultados->map(fn ($c) => [
            'id' => $c->id, 
            'texto' => "Caso #{$c->numero_caso} (ID: {$c->id})" . ($c->deudor ? " - {$c->deudor->nombre_completo}" : "")
        ]));
    }

    /**
     * Busca Contratos
     */
    public function buscarContratos(Request $request)
    {
        $queryTerm = $request->q;
        // CORRECCIÓN: Usar LOWER() y LIKE
        $queryLike = "%" . strtolower($queryTerm) . "%";
        $baseQuery = Contrato::with('cliente:id,nombre_completo');

        if (!$queryTerm) {
            $resultados = $baseQuery->latest()->limit(10)->get();
        } else {
            $resultados = $baseQuery
                ->where(function ($q) use ($queryLike) { 
                       $q->whereRaw('LOWER(objeto) LIKE ?', [$queryLike]) // Asumiendo que 'objeto' existe
                         // CORRECCIÓN: Usar LIKE estándar para el CAST
                         ->orWhereRaw('CAST(id AS TEXT) LIKE ?', [$queryLike])
                         ->orWhereHas('cliente', function ($subQ) use ($queryLike) { 
                            $subQ->whereRaw('LOWER(nombre_completo) LIKE ?', [$queryLike]);
                         });
                }) 
                ->limit(10)->get();
        }
        return response()->json($resultados->map(fn ($c) => [
            'id' => $c->id, 
            'texto' => "Contrato #{$c->id}" . ($c->cliente ? " - {$c->cliente->nombre_completo}" : "")
        ]));
    }
    // --- FIN: MÉTODOS DE BÚSQUEDA CORREGIDOS ---
}