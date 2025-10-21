<?php

namespace App\Http\Controllers;

use App\Models\ProcesoRadicado;
use App\Models\DocumentoProceso;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class DocumentoProcesoController extends Controller
{
    public function store(Request $request, ProcesoRadicado $proceso)
    {
        $data = $request->validate([
            'archivo' => ['required', 'file', 'max:102400'], // 100MB
            'nombre'  => ['required', 'string', 'max:255'],
            'nota'    => ['nullable', 'string', 'max:2000'],
        ]);

        $file = $request->file('archivo');
        $dir  = "procesos/{$proceso->id}";
        $path = $file->store($dir, 'public');

        DocumentoProceso::create([
            'proceso_radicado_id' => $proceso->id,
            'user_id'             => $request->user()->id ?? null,
            'descripcion'         => $data['nota'] ?? null, // ahora puede ser NULL
            'file_name'           => $data['nombre'],
            'file_path'           => $path,
        ]);

        return back()->with('success', 'Documento cargado correctamente.');
    }

    public function view(DocumentoProceso $documento)
    {
        if (!$documento->file_path || !Storage::disk('public')->exists($documento->file_path)) {
            abort(404);
        }
        $mime = Storage::disk('public')->mimeType($documento->file_path) ?: 'application/octet-stream';
        return response(Storage::disk('public')->get($documento->file_path), 200, [
            'Content-Type'        => $mime,
            'Content-Disposition' => 'inline; filename="'.addslashes($documento->file_name ?: 'archivo').'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function download(DocumentoProceso $documento)
    {
        if (!$documento->file_path || !Storage::disk('public')->exists($documento->file_path)) {
            abort(404);
        }
        return Storage::disk('public')->download($documento->file_path, $documento->file_name ?: 'archivo');
    }

    public function destroy(ProcesoRadicado $proceso, DocumentoProceso $documento)
    {
        if ((int) $documento->proceso_radicado_id !== (int) $proceso->id) {
            abort(Response::HTTP_FORBIDDEN);
        }
        if ($documento->file_path && Storage::disk('public')->exists($documento->file_path)) {
            Storage::disk('public')->delete($documento->file_path);
        }
        $documento->delete();
        return back()->with('success', 'Documento eliminado.');
    }
}
