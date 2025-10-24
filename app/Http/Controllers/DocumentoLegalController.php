<?php

namespace App\Http\Controllers;

use App\Models\Cooperativa;
use App\Models\DocumentoLegal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; // <-- 1. LÍNEA AÑADIDA

class DocumentoLegalController extends Controller
{
    use AuthorizesRequests; // <-- 2. LÍNEA AÑADIDA

    /**
     * Almacena un nuevo documento para una cooperativa.
     */
    public function store(Request $request, Cooperativa $cooperativa)
    {
        // Añadimos autorización para asegurarnos de que el usuario puede actuar sobre esta cooperativa
        $this->authorize('update', $cooperativa);

        $request->validate([
            'tipo_documento' => 'required|string|max:255',
            'fecha_expedicion' => 'required|date',
            'fecha_vencimiento' => 'nullable|date|after_or_equal:fecha_expedicion',
            'archivo' => 'required|file|mimes:pdf,jpg,jpeg,png|max:131072',
        ]);

        $path = $request->file('archivo')->store('documentos_legales', 'public');

        $cooperativa->documentos()->create([
            'tipo_documento' => $request->tipo_documento,
            'fecha_expedicion' => $request->fecha_expedicion,
            'fecha_vencimiento' => $request->fecha_vencimiento,
            'archivo' => $path,
        ]);

        // Usamos Redirect::back() que es más flexible si se llama desde otras vistas
        return Redirect::back()->with('success', '¡Documento subido exitosamente!');
    }

    /**
     * Muestra el documento solicitado, sirviéndolo de forma segura.
     * Esto soluciona el error 404 de forma definitiva.
     */
    public function show(DocumentoLegal $documento)
    {
        // Autorizamos que el usuario actual pueda ver la cooperativa a la que pertenece el documento
        $this->authorize('view', $documento->cooperativa);

        // Verificamos que el archivo exista físicamente en el disco
        if (!Storage::disk('public')->exists($documento->archivo)) {
            abort(404, 'El archivo no ha sido encontrado en el servidor.');
        }

        // Construimos la ruta completa al archivo y lo devolvemos
        $path = storage_path('app/public/' . $documento->archivo);
        return response()->file($path);
    }

    /**
     * Elimina un documento específico.
     */
    public function destroy(DocumentoLegal $documento)
    {
        // Añadimos autorización para el borrado
        $this->authorize('update', $documento->cooperativa);

        // Eliminamos el archivo del disco
        if (Storage::disk('public')->exists($documento->archivo)) {
            Storage::disk('public')->delete($documento->archivo);
        }

        // Eliminamos el registro de la base de datos
        $documento->delete();

        return Redirect::back()->with('success', '¡Documento eliminado exitosamente!');
    }
}

