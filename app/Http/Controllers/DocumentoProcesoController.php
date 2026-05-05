<?php

namespace App\Http\Controllers;

use App\Models\ProcesoRadicado;
use App\Models\DocumentoProceso;
use App\Models\AuditoriaEvento; // ✅ Auditoría
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth; // ✅ Auth
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class DocumentoProcesoController extends Controller
{
    use AuthorizesRequests;

    public function store(Request $request, ProcesoRadicado $proceso)
    {
        $data = $request->validate([
            'documentos' => ['required', 'array', 'min:1'],
            'documentos.*.archivo' => ['required', 'file', 'max:102400'], // 100MB
            'documentos.*.nombre'  => ['required', 'string', 'max:255'],
            'documentos.*.nota'    => ['nullable', 'string', 'max:2000'],
            'fecha_proxima_revision' => ['required', 'date', 'after_or_equal:today'],
        ]);

        \DB::transaction(function () use ($proceso, $data, $request) {
            foreach ($data['documentos'] as $docData) {
                $file = $docData['archivo'];
                $dir  = "procesos/{$proceso->id}";
                $path = $file->store($dir, 'public');

                DocumentoProceso::create([
                    'proceso_radicado_id' => $proceso->id,
                    'user_id'             => $request->user()->id ?? null,
                    'descripcion'         => $docData['nota'] ?? null,
                    'file_name'           => $docData['nombre'],
                    'file_path'           => $path,
                ]);

                // ✅ AUDITORÍA POR CADA DOCUMENTO
                $proceso->auditoria()->create([
                    'user_id' => Auth::id(),
                    'evento' => 'SUBIR_DOCUMENTO_PROCESO',
                    'descripcion_breve' => "Subido documento '{$docData['nombre']}' al radicado {$proceso->radicado}",
                    'criticidad' => 'media',
                    'direccion_ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            }

            $proceso->update(['fecha_proxima_revision' => $data['fecha_proxima_revision']]);
            
            // Auditoría del cambio de fecha
            $proceso->auditoria()->create([
                'user_id' => Auth::id(),
                'evento' => 'ACTUALIZAR_REVISION_PROCESO',
                'descripcion_breve' => "Actualizada próxima revisión a {$data['fecha_proxima_revision']} tras subida masiva de documentos",
                'criticidad' => 'baja',
                'direccion_ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        });

        return back()->with('success', 'Documentos cargados y próxima revisión actualizada.');
    }

    public function view(DocumentoProceso $documento)
    {
        $this->authorize('view', $documento->proceso);

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
        $this->authorize('view', $documento->proceso);

        if (!$documento->file_path || !Storage::disk('public')->exists($documento->file_path)) {
            abort(404);
        }
        return Storage::disk('public')->download($documento->file_path, $documento->file_name ?: 'archivo');
    }

    public function destroy(ProcesoRadicado $proceso, DocumentoProceso $documento)
    {
        $this->authorize('update', $proceso);

        if ((int) $documento->proceso_radicado_id !== (int) $proceso->id) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $nombreDoc = $documento->file_name; // Guardar nombre para el log

        if ($documento->file_path && Storage::disk('public')->exists($documento->file_path)) {
            Storage::disk('public')->delete($documento->file_path);
        }
        $documento->delete();

        // ✅ AUDITORÍA GLOBAL
        $proceso->auditoria()->create([
            'user_id' => Auth::id(),
            'evento' => 'ELIMINAR_DOCUMENTO_PROCESO',
            'descripcion_breve' => "Eliminado documento '{$nombreDoc}' del radicado {$proceso->radicado}",
            'criticidad' => 'alta',
            'direccion_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return back()->with('success', 'Documento eliminado.');
    }
}