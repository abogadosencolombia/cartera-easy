<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use App\Models\PersonaDocumento; // ✅ Importar modelo
use App\Models\Cooperativa;
use App\Models\User;
use App\Models\AuditoriaEvento;
use App\Http\Requests\StorePersonaRequest;
use App\Http\Requests\UpdatePersonaRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage; // ✅ Importar Storage
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Redirect;
use App\Exports\PersonasExport; 
use Maatwebsite\Excel\Facades\Excel;

class PersonaController extends Controller
{
    public function index(Request $request): Response
    {
        // ... (Tu código existente del index se mantiene igual)
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
        
        // ... (filtros existentes)

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
        // ... (Tu código store existente)
        $persona = Persona::create($request->validated());
        
        if ($request->has('cooperativas_ids')) {
            $persona->cooperativas()->sync($request->input('cooperativas_ids'));
        }
        if ($request->has('abogados_ids')) {
            $persona->abogados()->sync($request->input('abogados_ids'));
        }

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

    public function show($id): Response
    {
        $persona = Persona::withTrashed()
            ->with([
                'cooperativas', 
                'abogados',
                'casos' => fn($query) => $query->with('user:id,name')->latest()->take(10),
                'procesos' => fn($query) => $query->latest('fecha_radicado')->take(10),
                'documentos.uploader' // ✅ Cargar documentos y quién los subió
            ])
            ->withCount(['casos', 'procesos', 'documentos']) // ✅ Contar documentos
            ->findOrFail($id);

        return Inertia::render('Personas/Show', [
            'persona' => $persona,
        ]);
    }

    public function edit(Persona $persona): Response
    {
        // ... (Tu código edit existente)
        $persona->load(['cooperativas', 'abogados']);
        return Inertia::render('Personas/Edit', [
            'persona' => $persona,
            'allCooperativas' => Cooperativa::orderBy('nombre')->get(['id', 'nombre']),
            'allAbogados' => User::whereIn('tipo_usuario', ['abogado', 'gestor'])->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdatePersonaRequest $request, Persona $persona)
    {
        // ... (Tu código update existente)
        $persona->update($request->validated());

        if ($request->has('cooperativas_ids')) {
            $persona->cooperativas()->sync($request->input('cooperativas_ids'));
        }
        if ($request->has('abogados_ids')) {
            $persona->abogados()->sync($request->input('abogados_ids'));
        }

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
        // ... (Tu código destroy existente)
        $nombre = $persona->nombre_completo;
        $persona->delete();

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
        // ... (Tu código restore existente)
        $persona = Persona::withTrashed()->findOrFail($id);
        $persona->restore();

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
        // ... (Tu código export existente)
        AuditoriaEvento::create([
            'user_id' => Auth::id(),
            'evento' => 'EXPORTAR_PERSONAS',
            'descripcion_breve' => "Descarga masiva de listado de personas en Excel",
            'criticidad' => 'media',
            'direccion_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return Excel::download(new PersonasExport($request->query()), 'personas.xlsx');
    }

    // ==========================================================
    // ✅ NUEVAS FUNCIONES PARA DOCUMENTOS
    // ==========================================================

    public function uploadDocument(Request $request, $personaId)
    {
        $request->validate([
            'documento' => 'required|file|max:20480', // Máx 20MB
        ]);

        $persona = Persona::findOrFail($personaId);
        $file = $request->file('documento');
        $nombreOriginal = $file->getClientOriginalName();
        
        // Guardar en storage privado (no public) para seguridad
        $path = $file->storeAs("personas/{$persona->id}", time() . '_' . $nombreOriginal);

        $persona->documentos()->create([
            'nombre_original' => $nombreOriginal,
            'ruta_archivo' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => Auth::id(),
        ]);

        AuditoriaEvento::create([
            'user_id' => Auth::id(),
            'evento' => 'SUBIR_DOCUMENTO',
            'descripcion_breve' => "Documento subido a {$persona->nombre_completo}: {$nombreOriginal}",
            'criticidad' => 'baja',
            'direccion_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return back()->with('success', 'Documento subido correctamente.');
    }

    public function downloadDocument($documentoId)
    {
        $documento = PersonaDocumento::findOrFail($documentoId);

        if (!Storage::exists($documento->ruta_archivo)) {
            return back()->with('error', 'El archivo físico no se encuentra.');
        }

        return Storage::download($documento->ruta_archivo, $documento->nombre_original);
    }

    public function viewDocument($documentoId)
    {
        $documento = PersonaDocumento::findOrFail($documentoId);

        if (!Storage::exists($documento->ruta_archivo)) {
            abort(404);
        }

        // 'response' intenta mostrarlo en el navegador si es posible (PDF, JPG, PNG)
        return Storage::response($documento->ruta_archivo, $documento->nombre_original);
    }

    public function deleteDocument($documentoId)
    {
        $documento = PersonaDocumento::findOrFail($documentoId);
        
        // Borrar archivo físico
        if (Storage::exists($documento->ruta_archivo)) {
            Storage::delete($documento->ruta_archivo);
        }

        // Borrar registro DB
        $documento->delete();

        AuditoriaEvento::create([
            'user_id' => Auth::id(),
            'evento' => 'ELIMINAR_DOCUMENTO',
            'descripcion_breve' => "Documento eliminado: {$documento->nombre_original}",
            'criticidad' => 'media',
            'direccion_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return back()->with('success', 'Documento eliminado correctamente.');
    }
}