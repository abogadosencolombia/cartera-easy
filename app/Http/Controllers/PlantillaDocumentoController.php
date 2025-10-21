<?php

namespace App\Http\Controllers;

use App\Models\PlantillaDocumento;
use App\Models\Cooperativa;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;

class PlantillaDocumentoController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PlantillaDocumento::class);
        $user = Auth::user();
        
        $query = PlantillaDocumento::with('cooperativa')->withCount('documentosGenerados');

        if ($user->tipo_usuario !== 'admin') {
            $cooperativaIds = $user->cooperativas->pluck('id');
            $query->whereIn('cooperativa_id', $cooperativaIds);
        }

        // --- LÓGICA DE FILTRADO AÑADIDA ---
        $query->when($request->input('tipo'), function ($q, $tipo) {
            $q->where('tipo', $tipo);
        });

        $query->when($request->filled('activa'), function ($q) use ($request) {
            $q->where('activa', $request->input('activa'));
        });
        // --- FIN DE LA LÓGICA DE FILTRADO ---

        return Inertia::render('Plantillas/Index', [
            'plantillas' => $query->get(),
            'cooperativas' => Cooperativa::all(['id', 'nombre']),
            'tipos_proceso' => ['ejecutivo singular', 'hipotecario', 'prendario', 'libranza'],
            'filtros' => $request->only(['tipo', 'activa']), // Pasamos los filtros a la vista
            'can' => [
                'create_plantillas' => $user->can('create', PlantillaDocumento::class),
            ]
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', PlantillaDocumento::class);
        $validated = $request->validate([
            'cooperativa_id' => ['nullable', 'exists:cooperativas,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'tipo' => ['required', Rule::in(['demanda', 'carta', 'medida cautelar', 'notificación', 'otros'])],
            'version' => ['nullable', 'string', 'max:50'],
            'aplica_a' => ['nullable', 'array'],
            'archivo' => ['required', 'file', 'mimes:docx', 'max:5120'],
        ]);

        $path = $request->file('archivo')->store('plantillas', 'local');

        PlantillaDocumento::create([
            'cooperativa_id' => $validated['cooperativa_id'],
            'nombre' => $validated['nombre'],
            'tipo' => $validated['tipo'],
            'version' => $validated['version'] ?? '1.0',
            'aplica_a' => $validated['aplica_a'],
            'archivo' => $path,
        ]);

        return to_route('plantillas.index')->with('success', '¡Plantilla guardada exitosamente!');
    }
    
    public function clonar(PlantillaDocumento $plantilla): RedirectResponse
    {
        $this->authorize('create', PlantillaDocumento::class);

        if (!Storage::disk('local')->exists($plantilla->archivo)) {
            return back()->with('error', 'El archivo original de la plantilla no existe y no puede ser clonado.');
        }
        
        $nuevoNombreArchivo = 'plantillas/' . Str::uuid() . '.docx';
        Storage::disk('local')->copy($plantilla->archivo, $nuevoNombreArchivo);

        $nuevaPlantilla = $plantilla->replicate();
        $nuevaPlantilla->nombre = $plantilla->nombre . ' (Copia)';
        $nuevaPlantilla->archivo = $nuevoNombreArchivo;
        $nuevaPlantilla->created_at = now();
        $nuevaPlantilla->updated_at = now();
        $nuevaPlantilla->save();

        return to_route('plantillas.index')->with('success', '¡Plantilla clonada exitosamente!');
    }

    public function destroy(PlantillaDocumento $plantilla): RedirectResponse
    {
        $this->authorize('delete', $plantilla);

        if ($plantilla->documentosGenerados()->count() > 0) {
            return back()->with('error', 'No se puede eliminar una plantilla que ya ha sido utilizada en documentos.');
        }
        
        if ($plantilla->archivo && Storage::disk('local')->exists($plantilla->archivo)) {
            Storage::disk('local')->delete($plantilla->archivo);
        }

        $plantilla->delete();

        return to_route('plantillas.index')->with('success', '¡Plantilla eliminada!');
    }
}
