<?php

namespace App\Http\Controllers;

use App\Models\Caso;
use App\Models\DocumentoCaso;
use App\Models\Persona;
use App\Models\Codeudor;
use App\Models\AuditoriaEvento;
use App\Traits\RegistraRevisionTrait;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentoCasoController extends Controller
{
    use AuthorizesRequests, RegistraRevisionTrait;

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Caso $caso): RedirectResponse
    {
        $this->authorize('update', $caso);

        // 1. Validaciones
        $validated = $request->validate([
            'tipo_documento' => [
                'required', 
                'string', 
                'max:255',
                'in:pagaré,carta instrucciones,certificación saldo,libranza,demanda,cédula deudor,cédula codeudor,otros'
            ],
            'fecha_carga' => 'required|date',
            'archivo' => 'required|file|mimes:pdf,jpg,png,jpeg,doc,docx|max:131072', // 128MB
            'nota' => 'nullable|string|max:5000',
            'asociado_a' => ['nullable', 'string'],
        ]);
        
        $personaId = null;
        $codeudorId = null;
        $comentarioBitacora = 'Se subió el documento: ' . $validated['tipo_documento'];

        // 2. Procesar la asociación polimórfica
        if (!empty($validated['asociado_a'])) {
            $parts = explode('-', $validated['asociado_a']);
            
            if (count($parts) !== 2) {
                throw ValidationException::withMessages(['asociado_a' => 'El formato de asociación no es válido.']);
            }

            $tipo = $parts[0];
            $id = $parts[1];

            if ($tipo === 'persona') {
                if ($caso->deudor_id == $id && Persona::where('id', $id)->exists()) {
                    $personaId = $id;
                    $comentarioBitacora .= ' (Asociado al Deudor Principal)';
                } else {
                    throw ValidationException::withMessages(['asociado_a' => 'El deudor seleccionado no coincide con el deudor del caso.']);
                }
            } elseif ($tipo === 'codeudor') {
                if ($caso->codeudores()->where('codeudores.id', $id)->exists()) {
                    $codeudorId = $id;
                    $comentarioBitacora .= ' (Asociado a un Codeudor)';
                } else {
                    throw ValidationException::withMessages(['asociado_a' => 'El codeudor seleccionado no pertenece a este caso.']);
                }
            } else {
                throw ValidationException::withMessages(['asociado_a' => 'El tipo de asociación no es válido.']);
            }
        }
        
        // 3. Guardar el archivo
        $path = $request->file('archivo')->store('casos_documentos', 'local');

        // 4. Crear el registro en la BD
        $caso->documentos()->create([
            'tipo_documento' => $validated['tipo_documento'],
            'fecha_carga' => $validated['fecha_carga'],
            'archivo' => $path,
            'nota' => $validated['nota'],
            'persona_id' => $personaId,
            'codeudor_id' => $codeudorId,
        ]);

        // 5. Bitácora Local (Caso)
        $caso->bitacoras()->create([
            'user_id' => auth()->id(),
            'accion' => 'Documento Adjuntado',
            'comentario' => $comentarioBitacora,
        ]);

        // ✅ 6. AUDITORÍA GLOBAL (NUEVO)
        AuditoriaEvento::create([
            'user_id' => Auth::id(),
            'evento' => 'SUBIR_DOCUMENTO',
            'descripcion_breve' => "Se subió '{$validated['tipo_documento']}' al caso #{$caso->id}",
            'criticidad' => 'media',
            'direccion_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        // ✅ Registro de revisión diaria por acción
        $this->registrarRevisionAutomatica($caso);

        return back()->with('success', '¡Documento subido exitosamente!');
    }

    /**
     * Permite visualizar un documento almacenado de forma privada.
     */
    public function view(DocumentoCaso $documento): StreamedResponse
    {
        $this->authorize('view', $documento->caso);
        
        if (!Storage::disk('local')->exists($documento->archivo)) {
            abort(404, 'Archivo no encontrado.');
        }

        return Storage::disk('local')->response($documento->archivo);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DocumentoCaso $documento): RedirectResponse
    {
        $this->authorize('update', $documento->caso);

        // Guardamos datos para la auditoría antes de borrar
        $nombreDoc = $documento->tipo_documento;
        $casoId = $documento->caso_id;

        // 1. Eliminar archivo físico
        if (Storage::disk('local')->exists($documento->archivo)) {
            Storage::disk('local')->delete($documento->archivo);
        }

        // 2. Eliminar registro BD
        $documento->delete();

        // ✅ 3. AUDITORÍA GLOBAL (NUEVO)
        AuditoriaEvento::create([
            'user_id' => Auth::id(),
            'evento' => 'ELIMINAR_DOCUMENTO',
            'descripcion_breve' => "Se eliminó el documento '{$nombreDoc}' del caso #{$casoId}",
            'criticidad' => 'alta',
            'direccion_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return back()->with('success', '¡Documento eliminado exitosamente!');
    }
}