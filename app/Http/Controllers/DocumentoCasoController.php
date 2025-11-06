<?php

namespace App\Http\Controllers;

use App\Models\Caso;
use App\Models\DocumentoCaso;
use App\Models\Persona;
use App\Models\Codeudor;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; // ¡Importante!
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth; // <-- AÑADIDO
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule; // <-- AÑADIDO
use Illuminate\Validation\ValidationException; // <-- AÑADIDO
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentoCasoController extends Controller
{
    use AuthorizesRequests; // Entrenamos al controlador

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Caso $caso): RedirectResponse
    {
        // --- INICIO: CORRECCIÓN (LÓGICA POLIMÓRFICA REAL) ---

        $this->authorize('update', $caso);

        // 1. Reglas base
        // Ahora validamos 'asociado_a' en lugar de 'codeudor_id'
        $validated = $request->validate([
            'tipo_documento' => 'required|string|max:255',
            'fecha_carga' => 'required|date',
            'archivo' => 'required|file|mimes:pdf,jpg,png,jpeg,doc,docx|max:131072', // 128MB
            'nota' => 'nullable|string|max:5000',
            'asociado_a' => [
                'nullable', // Permite que sea nulo (asociado solo al caso)
                'string',
            ],
        ]);
        
        // --- INICIO: CORRECCIÓN (LÓGICA POLIMÓRFICA) ---
        // Estas variables guardarán el ID en la columna correcta.
        $personaId = null;
        $codeudorId = null;
        // --- FIN: CORRECCIÓN ---
        
        $comentarioBitacora = 'Se subió el documento: ' . $validated['tipo_documento'];

        // 2. Procesar la asociación polimórfica
        if (!empty($validated['asociado_a'])) {
            $parts = explode('-', $validated['asociado_a']);
            
            if (count($parts) !== 2) {
                throw ValidationException::withMessages([
                    'asociado_a' => 'El formato de asociación no es válido.',
                ]);
            }

            $tipo = $parts[0];
            $id = $parts[1];

            if ($tipo === 'persona') {
                // Es un Deudor (Persona). Validamos que sea el deudor de este caso.
                if ($caso->deudor_id == $id && Persona::where('id', $id)->exists()) {
                    // --- INICIO: CORRECCIÓN ---
                    $personaId = $id; // Guardamos en la variable persona_id
                    // --- FIN: CORRECCIÓN ---
                    $comentarioBitacora .= ' (Asociado al Deudor Principal)';
                } else {
                    throw ValidationException::withMessages([
                        'asociado_a' => 'El deudor seleccionado no coincide con el deudor del caso.',
                    ]);
                }
            } elseif ($tipo === 'codeudor') {
                // Es un Codeudor. Validamos que este codeudor pertenezca a este caso.
                if ($caso->codeudores()->where('codeudores.id', $id)->exists()) {
                    // --- INICIO: CORRECCIÓN ---
                    $codeudorId = $id; // Guardamos en la variable codeudor_id
                    // --- FIN: CORRECCIÓN ---
                    $comentarioBitacora .= ' (Asociado a un Codeudor)';
                } else {
                    throw ValidationException::withMessages([
                        'asociado_a' => 'El codeudor seleccionado no pertenece a este caso.',
                    ]);
                }
            } else {
                throw ValidationException::withMessages([
                    'asociado_a' => 'El tipo de asociación no es válido.',
                ]);
            }
        }
        
        // 3. Guardar el archivo
        $path = $request->file('archivo')->store('casos_documentos', 'local');

        // 4. Crear el registro en la BD con las columnas correctas
        // --- INICIO: CORRECCIÓN (LÓGICA POLIMÓRFICA) ---
        // Esto asume que tu tabla 'documentos_caso' TIENE AMBAS columnas.
        $caso->documentos()->create([
            'tipo_documento' => $validated['tipo_documento'],
            'fecha_carga' => $validated['fecha_carga'],
            'archivo' => $path,
            'nota' => $validated['nota'],
            'persona_id' => $personaId,     // <-- Se guarda el ID de la persona (o null)
            'codeudor_id' => $codeudorId,   // <-- Se guarda el ID del codeudor (o null)
        ]);
        // --- FIN: CORRECCIÓN ---

        $caso->bitacoras()->create([
            'user_id' => auth()->id(),
            'accion' => 'Documento Adjuntado',
            'comentario' => $comentarioBitacora, // (usando el comentario dinámico)
        ]);

        return back()->with('success', '¡Documento subido exitosamente!');
    }

    /**
     * Permite visualizar un documento almacenado de forma privada.
     */
    public function view(DocumentoCaso $documento): StreamedResponse
    {
        // 1. Verificamos si el usuario tiene permiso para ver el caso asociado a este documento.
        $this->authorize('view', $documento->caso);
        
        // --- MODIFICADO (CORREGIDO A 'archivo') ---
        // 2. Verificamos que el archivo exista en nuestra bóveda ('local').
        if (!Storage::disk('local')->exists($documento->archivo)) {
            abort(404, 'Archivo no encontrado.');
        }

        // --- MODIFICADO (CORREGIDO A 'archivo') ---
        // 3. Si todo está en orden, entregamos el archivo directamente al navegador.
        return Storage::disk('local')->response($documento->archivo);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DocumentoCaso $documento): RedirectResponse
    {
        // 1. Verificamos si el usuario puede actualizar (y por ende, borrar documentos) el caso.
        $this->authorize('update', $documento->caso);

        // --- MODIFICADO (CORREGIDO A 'archivo') ---
        // 2. Eliminamos el archivo físico de nuestra bóveda.
        if (Storage::disk('local')->exists($documento->archivo)) {
            Storage::disk('local')->delete($documento->archivo);
        }

        // 3. Eliminamos el registro de la base de datos.
        $documento->delete();

        return back()->with('success', '¡Documento eliminado exitosamente!');
    }
}

