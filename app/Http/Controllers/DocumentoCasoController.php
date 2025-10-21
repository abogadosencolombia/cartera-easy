<?php

namespace App\Http\Controllers;

use App\Models\Caso;
use App\Models\DocumentoCaso;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; // ¡Importante!
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentoCasoController extends Controller
{
    use AuthorizesRequests; // Entrenamos al controlador

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Caso $caso): RedirectResponse
    {
        $this->authorize('update', $caso);

        $request->validate([
            'tipo_documento' => 'required|string',
            'fecha_carga' => 'required|date',
            'archivo' => 'required|file|mimes:pdf,jpg,png,jpeg|max:5120', // 5MB Max
        ]);
        
        $path = $request->file('archivo')->store('casos_documentos', 'local');

        $caso->documentos()->create([
            'tipo_documento' => $request->tipo_documento,
            'fecha_carga' => $request->fecha_carga,
            'archivo' => $path, 
        ]);

        $caso->bitacoras()->create([
            'user_id' => auth()->id(),
            'accion' => 'Documento Adjuntado',
            'comentario' => 'Se subió el documento: ' . $request->tipo_documento,
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
        
        // 2. Verificamos que el archivo exista en nuestra bóveda ('local').
        if (!Storage::disk('local')->exists($documento->archivo)) {
            abort(404, 'Archivo no encontrado.');
        }

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

        // 2. Eliminamos el archivo físico de nuestra bóveda.
        if (Storage::disk('local')->exists($documento->archivo)) {
            Storage::disk('local')->delete($documento->archivo);
        }

        // 3. Eliminamos el registro de la base de datos.
        $documento->delete();

        return back()->with('success', '¡Documento eliminado exitosamente!');
    }
}
