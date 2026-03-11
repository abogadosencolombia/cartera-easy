<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use App\Models\PersonaDocumento;
use App\Models\Cooperativa;
use App\Models\User;
use App\Models\Caso;
use App\Models\ProcesoRadicado;
use App\Models\AuditoriaEvento;
use App\Http\Requests\StorePersonaRequest;
use App\Http\Requests\UpdatePersonaRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Redirect;
use App\Exports\PersonasExport; 
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class PersonaController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Persona::class);

        $user = Auth::user();
        $query = Persona::with(['cooperativas', 'abogados']);

        // --- FILTRO DE SEGURIDAD POR COOPERATIVA ---
        if ($user->tipo_usuario !== 'admin') {
            $cooperativaIds = $user->cooperativas->pluck('id');
            $query->whereHas('cooperativas', function ($q) use ($cooperativaIds) {
                $q->whereIn('cooperativas.id', $cooperativaIds);
            });
        }

        // --- 1. Filtro de Estado (Activos / Suspendidos) ---
        if ($request->input('status') === 'suspended') {
            $query->onlyTrashed();
        }

        // --- 2. Búsqueda General ---
        $query->when($request->input('search'), function ($q, $search) {
            $q->where(function ($subq) use ($search) {
                $subq->where('nombre_completo', 'ilike', "%{$search}%")
                     ->orWhere('numero_documento', 'ilike', "%{$search}%")
                     ->orWhere('id', 'ilike', "%{$search}%");
            });
        });

        // --- 3. Filtro por Cooperativa ---
        $query->when($request->input('cooperativa_id'), function ($q, $coopId) {
            $q->whereHas('cooperativas', function ($sq) use ($coopId) {
                $sq->where('cooperativas.id', $coopId);
            });
        });

        // --- 4. Filtro por Abogado ---
        $query->when($request->input('abogado_id'), function ($q, $abogadoId) {
            $q->whereHas('abogados', function ($sq) use ($abogadoId) {
                $sq->where('users.id', $abogadoId);
            });
        });

        // --- 5. FILTRO NUEVO: Por Tipo (Deudor / Demandado) ---
        $query->when($request->input('tipo_rol'), function ($q, $tipo) {
            if ($tipo === 'demandado') {
                $q->where('es_demandado', true);
            } elseif ($tipo === 'deudor') {
                $q->where('es_demandado', false);
            }
        });

        // --- 6. Ordenamiento ---
        $sort = $request->input('sort', 'created_at');
        $direction = $request->input('direction', 'desc');
        $query->orderBy($sort, $direction);

        return Inertia::render('Personas/Index', [
            'personas' => $query->paginate(15)->withQueryString(),
            'filters' => $request->all(['search', 'status', 'cooperativa_id', 'abogado_id', 'tipo_rol', 'sort', 'direction']),
            'cooperativas' => Cooperativa::orderBy('nombre')->get(['id', 'nombre']),
            'abogados' => User::whereIn('tipo_usuario', ['abogado', 'gestor'])->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Persona::class);

        return Inertia::render('Personas/Create', [
            'allCooperativas' => Cooperativa::orderBy('nombre')->get(['id', 'nombre']),
            'allAbogados' => User::whereIn('tipo_usuario', ['abogado', 'gestor'])->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StorePersonaRequest $request)
    {
        $this->authorize('create', Persona::class);

        $validated = $request->validated();

        $persona = Persona::withTrashed()->updateOrCreate(
            ['numero_documento' => $validated['numero_documento']],
            array_merge($validated, ['deleted_at' => null])
        );
        
        if ($request->has('cooperativas_ids')) $persona->cooperativas()->sync($request->input('cooperativas_ids'));
        if ($request->has('abogados_ids')) $persona->abogados()->sync($request->input('abogados_ids'));

        AuditoriaEvento::create([
            'user_id' => Auth::id(), 'evento' => 'CREAR_PERSONA',
            'descripcion_breve' => "Creada o reactivada persona: {$persona->nombre_completo} ({$persona->numero_documento})",
            'criticidad' => 'media', 'direccion_ip' => request()->ip(), 'user_agent' => request()->userAgent(),
        ]);

        return redirect()->route('personas.index')->with('success', 'Persona registrada exitosamente.');
    }

    public function show($id): Response
    {
        $persona = Persona::withTrashed()->findOrFail($id);
        
        $this->authorize('view', $persona);

        // --- CARGAR CASOS DONDE ES DEUDOR O CODEUDOR ---
        // Buscamos casos donde es deudor_id
        // Y TAMBIÉN casos donde es codeudor (buscando por su número de documento en la tabla codeudores)
        $documento = $persona->numero_documento;
        
        $casosQuery = \App\Models\Caso::with('user:id,name')
            ->where(function($q) use ($persona, $documento) {
                $q->where('deudor_id', $persona->id)
                  ->orWhereHas('codeudores', function($sq) use ($documento) {
                      $sq->where('numero_documento', $documento);
                  });
            })
            ->latest();

        $casos = $casosQuery->take(10)->get();
        $casosCount = $casosQuery->count();

        // --- CARGAR PROCESOS DONDE ES DEMANDANTE O DEMANDADO ---
        $procesosQuery = \App\Models\ProcesoRadicado::whereHas('demandados', function($q) use ($persona) {
                $q->where('personas.id', $persona->id);
            })
            ->orWhereHas('demandantes', function($q) use ($persona) {
                $q->where('personas.id', $persona->id);
            })
            ->latest('updated_at'); // Usamos updated_at por si fecha_radicado es null

        $procesos = $procesosQuery->take(10)->get();
        $procesosCount = $procesosQuery->count();

        $persona->load([
            'cooperativas', 
            'abogados',
            'documentos.uploader'
        ]);
        
        // Seteamos las colecciones cargadas manualmente
        $persona->setRelation('casos', $casos);
        $persona->setRelation('procesos', $procesos);
        
        // Actualizamos los contadores
        $persona->casos_count = $casosCount;
        $persona->procesos_count = $procesosCount;
        $persona->documentos_count = $persona->documentos()->count();

        return Inertia::render('Personas/Show', ['persona' => $persona]);
    }

    public function edit($id): Response
    {
        $persona = Persona::withTrashed()->findOrFail($id);
        
        $this->authorize('update', $persona);

        $persona->load(['cooperativas', 'abogados']);
        return Inertia::render('Personas/Edit', [
            'persona' => $persona,
            'allCooperativas' => Cooperativa::orderBy('nombre')->get(['id', 'nombre']),
            'allAbogados' => User::whereIn('tipo_usuario', ['abogado', 'gestor'])->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdatePersonaRequest $request, $id)
    {
        $persona = Persona::withTrashed()->findOrFail($id);
        
        $this->authorize('update', $persona);

        $persona->update($request->validated());
        if ($request->has('cooperativas_ids')) $persona->cooperativas()->sync($request->input('cooperativas_ids'));
        if ($request->has('abogados_ids')) $persona->abogados()->sync($request->input('abogados_ids'));

        AuditoriaEvento::create([
            'user_id' => Auth::id(), 'evento' => 'EDITAR_PERSONA',
            'descripcion_breve' => "Actualizados datos de {$persona->nombre_completo}",
            'criticidad' => 'baja', 'direccion_ip' => request()->ip(), 'user_agent' => request()->userAgent(),
        ]);

        return redirect()->route('personas.index')->with('success', 'Persona actualizada correctamente.');
    }

    public function destroy($id)
    {
        $persona = Persona::withTrashed()->findOrFail($id);
        
        $this->authorize('delete', $persona);

        $nombre = $persona->nombre_completo;
        $persona->delete();
        AuditoriaEvento::create([
            'user_id' => Auth::id(), 'evento' => 'SUSPENDER_PERSONA',
            'descripcion_breve' => "Suspendida: {$nombre}",
            'criticidad' => 'alta', 'direccion_ip' => request()->ip(), 'user_agent' => request()->userAgent(),
        ]);
        return redirect()->route('personas.index')->with('success', 'Persona suspendida.');
    }

    public function restore($id)
    {
        $persona = Persona::withTrashed()->findOrFail($id);
        
        $this->authorize('restore', $persona);

        $persona->restore();
        AuditoriaEvento::create([
            'user_id' => Auth::id(), 'evento' => 'REACTIVAR_PERSONA',
            'descripcion_breve' => "Reactivada: {$persona->nombre_completo}",
            'criticidad' => 'alta', 'direccion_ip' => request()->ip(), 'user_agent' => request()->userAgent(),
        ]);
        return back()->with('success', 'Persona reactivada.');
    }
    
    public function exportExcel(Request $request) 
    {
        $this->authorize('viewAny', Persona::class);

        AuditoriaEvento::create([
            'user_id' => Auth::id(), 'evento' => 'EXPORTAR_PERSONAS',
            'descripcion_breve' => "Descarga Excel de personas con filtros activos",
            'criticidad' => 'media', 'direccion_ip' => $request->ip(), 'user_agent' => $request->userAgent(),
        ]);

        return Excel::download(new PersonasExport($request->query()), 'listado_personas_' . now()->format('Ymd_His') . '.xlsx');
    }

    public function uploadDocument(Request $request, $personaId)
    {
        $persona = Persona::findOrFail($personaId);
        
        $this->authorize('update', $persona);

        $request->validate(['documento' => 'required|file|max:20480']);
        $file = $request->file('documento');
        $nombreOriginal = $file->getClientOriginalName();
        $path = $file->storeAs("personas/{$persona->id}", time() . '_' . $nombreOriginal);
        $persona->documentos()->create([
            'nombre_original' => $nombreOriginal, 'ruta_archivo' => $path,
            'mime_type' => $file->getMimeType(), 'size' => $file->getSize(), 'uploaded_by' => Auth::id(),
        ]);
        return back()->with('success', 'Archivo subido.');
    }

    public function downloadDocument($id) { 
        $doc = PersonaDocumento::findOrFail($id); 
        $this->authorize('view', $doc->persona);
        return Storage::download($doc->ruta_archivo, $doc->nombre_original); 
    }

    public function viewDocument($id) { 
        $doc = PersonaDocumento::findOrFail($id); 
        $this->authorize('view', $doc->persona);
        return Storage::response($doc->ruta_archivo); 
    }

    public function deleteDocument($id) { 
        $doc = PersonaDocumento::findOrFail($id); 
        $this->authorize('update', $doc->persona);
        Storage::delete($doc->ruta_archivo); 
        $doc->delete(); 
        return back()->with('success', 'Documento eliminado.'); 
    }
}
