<?php

namespace App\Http\Controllers\Juridico;

use App\Http\Controllers\Controller;
use App\Models\ArchivoIncidente;
use App\Models\IncidenteJuridico;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ArchivoIncidenteController extends Controller
{
    /**
     * Almacena un nuevo archivo de evidencia para un incidente.
     */
    public function store(Request $request, IncidenteJuridico $incidente)
    {
        $request->validate([
            // Validamos que el archivo exista, que no pese más de 5MB (5120 KB)
            // y que sea de un tipo permitido.
            'archivo' => 'required|file|max:5120|mimes:pdf,docx,doc,jpg,jpeg,png,webp',
        ]);

        // Guardamos el archivo en una carpeta segura y obtenemos su ruta.
        // Usamos el disco 'public' para que luego sea accesible.
        $path = $request->file('archivo')->store('evidencias_juridicas', 'public');

        // Creamos el registro en la base de datos
        $incidente->archivos()->create([
            'subido_por_id' => Auth::id(),
            'nombre_original' => $request->file('archivo')->getClientOriginalName(),
            'ruta_archivo' => $path,
            'tipo_archivo' => $request->file('archivo')->getClientMimeType(),
        ]);

        return back()->with('success', 'Archivo subido con éxito.');
    }

    /**
     * Permite la descarga segura de un archivo de evidencia.
     */
    public function descargar(ArchivoIncidente $archivo)
    {
        // Verificamos que el archivo exista en nuestro almacenamiento
        if (!Storage::disk('public')->exists($archivo->ruta_archivo)) {
            abort(404, 'El archivo no fue encontrado.');
        }

        // Devolvemos el archivo para que el navegador inicie la descarga.
        return Storage::disk('public')->download($archivo->ruta_archivo, $archivo->nombre_original);
    }
}