<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Models\Tarea;
use App\Models\User;
use App\Models\Caso;
use App\Models\Contrato;
use App\Models\ProcesoRadicado;
use App\Notifications\NuevaTareaAsignada;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Carbon\Carbon;

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

        // ===== ORDENAMIENTO INTELIGENTE =====
        // 1. Vencidas (Urgencia máxima)
        // 2. Pendientes con fecha futura (Lo que sigue)
        // 3. Pendientes SIN fecha (Notas generales / Tareas sueltas)
        // 4. Completadas (Al final del todo)
        $tareas = $query->orderByRaw("
            CASE 
                WHEN estado = 'completada' THEN 4
                WHEN fecha_limite IS NOT NULL AND fecha_limite < NOW() THEN 1 
                WHEN fecha_limite IS NOT NULL THEN 2
                ELSE 3
            END ASC
        ")->orderBy('fecha_limite', 'asc')->paginate(20)->withQueryString();

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

        // Validamos, pero ahora permitimos nulos (nullable)
        $validated = $request->validate([
            'titulo' => 'required|string|max:255',
            'descripcion' => 'required|string|max:5000',
            'user_id' => 'required|exists:users,id',
            // Estos ahora son opcionales:
            'tarea_type' => 'nullable|string', 
            'tarea_id' => 'nullable|integer',
            'fecha_limite' => 'nullable|date|after:now', 
        ], [
            'fecha_limite.after' => 'Si pones fecha, debe ser en el futuro.',
        ]);

        // Lógica de vinculación (Solo si el usuario seleccionó algo)
        $modelClass = null;
        if (!empty($validated['tarea_type']) && !empty($validated['tarea_id'])) {
            $modelMap = [
                'proceso' => ProcesoRadicado::class,
                'caso' => Caso::class,
                'contrato' => Contrato::class,
            ];

            $modelClass = $modelMap[$validated['tarea_type']] ?? null;

            if (!$modelClass || !class_exists($modelClass)) {
                return back()->withErrors(['tarea_type' => 'Tipo de vinculación inválido.']);
            }
            
            // Verificar que el elemento exista
            if (!$modelClass::find($validated['tarea_id'])) {
                return back()->withErrors(['tarea_id' => 'El elemento vinculado no existe.']);
            }
        }

        // Crear la tarea (Manejando los nulos correctamente)
        try {
            $tarea = Tarea::create([
                'titulo' => $validated['titulo'],
                'descripcion' => $validated['descripcion'],
                'user_id' => $validated['user_id'],
                'admin_id' => Auth::id(),
                'tarea_type' => $modelClass, // Puede ser null
                'tarea_id' => $validated['tarea_id'] ?? null, // Puede ser null
                'estado' => 'pendiente',
                'fecha_limite' => $validated['fecha_limite'] ?? null, // Puede ser null
            ]);
            
            Log::info('Tarea creada ID: ' . $tarea->id);

        } catch (\Exception $e) {
            Log::error('Error DB al crear tarea: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Error de base de datos al guardar la tarea.']);
        }

        // Notificar al usuario
        $usuarioAsignado = User::find($validated['user_id']);
        if ($usuarioAsignado) {
            try {
                $usuarioAsignado->notify(new NuevaTareaAsignada($tarea));
            } catch (\Exception $e) {
                Log::error('Error enviando notificación: ' . $e->getMessage());
            }
        }

        return to_route('admin.tareas.index')->with('success', 'Tarea creada exitosamente.');
    }

    /**
     * Marca una tarea como completada.
     */
    public function marcarComoCompletada(Request $request, Tarea $tarea)
    {
        if (Auth::id() !== $tarea->user_id && !Auth::user()->hasRole('admin')) {
             abort(403, 'No autorizado.');
        }

        if ($tarea->estado === 'pendiente') {
            $tarea->update([
                'estado' => 'completada',
                'fecha_completado' => Carbon::now()
            ]);
            return back(303)->with('success', 'Tarea completada.');
        }
        return back(303);
    }

    /**
     * Elimina una tarea y limpia sus notificaciones.
     */
    public function destroy(Tarea $tarea)
    {
        $this->authorize('delete', $tarea);
        
        $searchString = '"tarea_id":' . $tarea->id;
        DatabaseNotification::where('type', \App\Notifications\NuevaTareaAsignada::class)
            ->where('data', 'LIKE', '%' . $searchString . '%')
            ->delete();
        
        $tarea->delete();
        
        return to_route('admin.tareas.index')->with('success', 'Tarea eliminada.');
    }

    // --- MÉTODOS DE BÚSQUEDA ---
    // Se mantienen igual para soportar el modal de búsqueda

    public function buscarProcesos(Request $request) { 
        $q = strtolower($request->q);
        if (!$q) return response()->json(ProcesoRadicado::orderBy('id', 'desc')->limit(10)->get()->map(fn($p)=>['id'=>$p->id,'texto'=>"Rad: {$p->radicado}"]));
        return response()->json(ProcesoRadicado::whereRaw('LOWER(radicado) LIKE ?', ["%$q%"])->limit(10)->get()->map(fn($p)=>['id'=>$p->id,'texto'=>"Rad: {$p->radicado}"]));
    }

    public function buscarCasos(Request $request) { 
        $q = strtolower($request->q);
        $query = Caso::with('deudor');
        if (!$q) return response()->json($query->orderBy('id', 'desc')->limit(10)->get()->map(fn($c)=>['id'=>$c->id,'texto'=>"Caso #{$c->numero_caso}"]));
        return response()->json($query->whereRaw('LOWER(numero_caso) LIKE ?', ["%$q%"])->limit(10)->get()->map(fn($c)=>['id'=>$c->id,'texto'=>"Caso #{$c->numero_caso}"]));
    }

    public function buscarContratos(Request $request) {
        $q = strtolower($request->q);
        $query = Contrato::with('cliente');
        if (!$q) return response()->json($query->orderBy('id', 'desc')->limit(10)->get()->map(fn($c)=>['id'=>$c->id,'texto'=>"Contrato #{$c->id}"]));
        return response()->json($query->whereRaw('LOWER(objeto) LIKE ?', ["%$q%"])->limit(10)->get()->map(fn($c)=>['id'=>$c->id,'texto'=>"Contrato #{$c->id}"]));
    }
}