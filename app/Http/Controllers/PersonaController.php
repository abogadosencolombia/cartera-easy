<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use App\Models\Cooperativa;
use App\Models\User;
use App\Models\AuditoriaEvento; // ✅ Auditoría
use App\Http\Requests\StorePersonaRequest;
use App\Http\Requests\UpdatePersonaRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; // ✅ Auth necesario
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Redirect;
use App\Exports\PersonasExport; 
use Maatwebsite\Excel\Facades\Excel;

class PersonaController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Persona::with(['cooperativas', 'abogados']);

        if ($request->input('status') === 'suspended') {
            $query->onlyTrashed();
        }

        $query->when($request->input('search'), function ($q, $search) {
            $q->where(function ($subq) use ($search) {
                $subq->where('nombre_completo', 'ilike', "%{$search}%")
                     ->orWhere('numero_documento', 'ilike', "%{$search}%")
                     ->orWhere('id', 'ilike', "%{$search}%");
            });
        });

        $query->when($request->input('cooperativa_id'), function ($q, $id) {
            $q->whereHas('cooperativas', function ($subq) use ($id) {
                $subq->where('cooperativas.id', $id);
            });
        });

        $query->when($request->input('abogado_id'), function ($q, $id) {
            $q->whereHas('abogados', function ($subq) use ($id) {
                $subq->where('users.id', $id);
            });
        });

        $sort = $request->input('sort', 'created_at');
        $direction = $request->input('direction', 'desc');
        $query->orderBy($sort, $direction);

        return Inertia::render('Personas/Index', [
            'personas' => $query->paginate(10)->withQueryString(),
            'filters' => $request->all(['search', 'status', 'cooperativa_id', 'abogado_id', 'sort', 'direction']),
            'cooperativas' => Cooperativa::orderBy('nombre')->get(['id', 'nombre']),
            'abogados' => User::whereIn('tipo_usuario', ['abogado', 'gestor'])->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Personas/Create', [
            'allCooperativas' => Cooperativa::orderBy('nombre')->get(['id', 'nombre']),
            'allAbogados' => User::whereIn('tipo_usuario', ['abogado', 'gestor'])->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StorePersonaRequest $request)
    {
        $persona = Persona::create($request->validated());
        
        if ($request->has('cooperativas_ids')) {
            $persona->cooperativas()->sync($request->input('cooperativas_ids'));
        }
        if ($request->has('abogados_ids')) {
            $persona->abogados()->sync($request->input('abogados_ids'));
        }

        // ✅ AUDITORÍA GLOBAL
        AuditoriaEvento::create([
            'user_id' => Auth::id(),
            'evento' => 'CREAR_PERSONA',
            'descripcion_breve' => "Creada persona: {$persona->nombre_completo} (Doc: {$persona->numero_documento})",
            'criticidad' => 'media',
            'direccion_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return redirect()->route('personas.index')->with('success', 'Persona creada exitosamente.');
    }

    // ✅ MÉTODO SHOW OPTIMIZADO: Carga rápida con límite de 10 items + contadores totales
    public function show($id): Response
    {
        $persona = Persona::withTrashed()
            ->with([
                'cooperativas', 
                'abogados',
                // ⚡ Carga solo los 10 casos más recientes para evitar lentitud
                'casos' => fn($query) => $query->with('user:id,name')->latest()->take(10),
                // ⚡ Carga solo los 10 procesos más recientes
                'procesos' => fn($query) => $query->latest('fecha_radicado')->take(10)
            ])
            // ⚡ Cuenta el total real en la base de datos para mostrarlo en los badges
            ->withCount(['casos', 'procesos'])
            ->findOrFail($id);

        return Inertia::render('Personas/Show', [
            'persona' => $persona,
        ]);
    }

    public function edit(Persona $persona): Response
    {
        $persona->load(['cooperativas', 'abogados']);
        return Inertia::render('Personas/Edit', [
            'persona' => $persona,
            'allCooperativas' => Cooperativa::orderBy('nombre')->get(['id', 'nombre']),
            'allAbogados' => User::whereIn('tipo_usuario', ['abogado', 'gestor'])->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdatePersonaRequest $request, Persona $persona)
    {
        $persona->update($request->validated());

        if ($request->has('cooperativas_ids')) {
            $persona->cooperativas()->sync($request->input('cooperativas_ids'));
        }
        if ($request->has('abogados_ids')) {
            $persona->abogados()->sync($request->input('abogados_ids'));
        }

        // ✅ AUDITORÍA GLOBAL
        AuditoriaEvento::create([
            'user_id' => Auth::id(),
            'evento' => 'EDITAR_PERSONA',
            'descripcion_breve' => "Actualizados datos de {$persona->nombre_completo}",
            'criticidad' => 'baja',
            'direccion_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return redirect()->route('personas.index')->with('success', 'Persona actualizada correctamente.');
    }

    public function destroy(Persona $persona)
    {
        $nombre = $persona->nombre_completo; // Guardar para log
        $persona->delete();

        // ✅ AUDITORÍA GLOBAL
        AuditoriaEvento::create([
            'user_id' => Auth::id(),
            'evento' => 'SUSPENDER_PERSONA',
            'descripcion_breve' => "Persona suspendida/eliminada: {$nombre}",
            'criticidad' => 'alta',
            'direccion_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return redirect()->route('personas.index')->with('success', 'Persona suspendida correctamente.');
    }

    public function restore($id)
    {
        $persona = Persona::withTrashed()->findOrFail($id);
        $persona->restore();

        // ✅ AUDITORÍA GLOBAL
        AuditoriaEvento::create([
            'user_id' => Auth::id(),
            'evento' => 'REACTIVAR_PERSONA',
            'descripcion_breve' => "Persona reactivada: {$persona->nombre_completo}",
            'criticidad' => 'alta',
            'direccion_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return redirect()->back()->with('success', 'Persona reactivada correctamente.');
    }
    
    public function exportExcel(Request $request) 
    {
        // ✅ AUDITORÍA GLOBAL (Opcional: saber quién se lleva la base de datos)
        AuditoriaEvento::create([
            'user_id' => Auth::id(),
            'evento' => 'EXPORTAR_PERSONAS',
            'descripcion_breve' => "Descarga masiva de listado de personas en Excel",
            'criticidad' => 'media', // Medio porque es fuga de datos potencial
            'direccion_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return Excel::download(new PersonasExport($request), 'personas.xlsx');
    }
}