<?php

namespace App\Http\Controllers;

use App\Models\Caso;
use App\Models\DocumentoCaso;
use App\Models\Persona;
use App\Models\Codeudor;
use App\Models\AuditoriaEvento; // ✅ IMPORTANTE: Importamos el modelo de auditoría
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

        $request->validate([
            'documentos' => ['required', 'array', 'min:1'],
            'documentos.*.tipo_documento' => [
                'required', 
                'string', 
                'max:255',
                Rule::in(['pagaré', 'carta instrucciones', 'certificación saldo', 'libranza', 'demanda', 'autos', 'memorial', 'cédula deudor', 'cédula codeudor', 'otros'])
            ],
            'documentos.*.fecha_carga' => 'required|date',
            'documentos.*.archivo' => 'required|file|mimes:pdf,jpg,png,jpeg,doc,docx,xls,xlsx,csv|max:131072', // 128MB
            'documentos.*.nota' => 'nullable|string|max:5000',
            'documentos.*.asociado_a' => ['nullable', 'string'],
        ]);

        \DB::transaction(function () use ($caso, $request) {
            foreach ($request->input('documentos') as $index => $docData) {
                $personaId = null;
                $codeudorId = null;
                $comentarioBitacora = 'Se subió el documento: ' . $docData['tipo_documento'];

                // Procesar la asociación polimórfica
                if (!empty($docData['asociado_a'])) {
                    $parts = explode('-', $docData['asociado_a']);
                    if (count($parts) === 2) {
                        $tipo = $parts[0];
                        $id = $parts[1];

                        if ($tipo === 'persona') {
                            if ($caso->deudor_id == $id) {
                                $personaId = $id;
                                $comentarioBitacora .= ' (Asociado al Deudor Principal)';
                            }
                        } elseif ($tipo === 'codeudor') {
                            if ($caso->codeudores()->where('codeudores.id', $id)->exists()) {
                                $codeudorId = $id;
                                $comentarioBitacora .= ' (Asociado a un Codeudor)';
                            }
                        }
                    }
                }

                // Guardar el archivo
                $file = $request->file("documentos.{$index}.archivo");
                $path = $file->store('casos_documentos', 'local');

                // Crear el registro en la BD
                $caso->documentos()->create([
                    'tipo_documento' => $docData['tipo_documento'],
                    'fecha_carga' => $docData['fecha_carga'],
                    'archivo' => $path,
                    'nota' => $docData['nota'] ?? null,
                    'persona_id' => $personaId,
                    'codeudor_id' => $codeudorId,
                ]);

                // Bitácora Local (Caso)
                $caso->bitacoras()->create([
                    'user_id' => auth()->id(),
                    'accion' => 'Documento Adjuntado',
                    'comentario' => $comentarioBitacora,
                ]);

                // AUDITORÍA GLOBAL
                AuditoriaEvento::create([
                    'user_id' => Auth::id(),
                    'evento' => 'SUBIR_DOCUMENTO',
                    'descripcion_breve' => "Se subió '{$docData['tipo_documento']}' al caso #{$caso->id}",
                    'criticidad' => 'media',
                    'direccion_ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            }
        });

        // Registro de revisión diaria por acción
        $this->registrarRevisionAutomatica($caso);

        return back()->with('success', '¡Documentos subidos exitosamente!');
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