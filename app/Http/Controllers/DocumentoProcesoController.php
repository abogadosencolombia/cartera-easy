<?php

namespace App\Http\Controllers;

use App\Models\ProcesoRadicado;
use App\Models\DocumentoProceso;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Services\ExpedienteIntegrityService;

class DocumentoProcesoController extends Controller
{
    use AuthorizesRequests;

    public function store(Request $request, ProcesoRadicado $proceso)
    {
        $this->authorize('update', $proceso);

        $data = $request->validate([
            'documentos' => ['required', 'array', 'min:1'],
            'documentos.*.archivo' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,csv,txt', 'max:102400'],
            'documentos.*.nombre'  => ['required', 'string', 'max:255'],
            'documentos.*.nota'    => ['nullable', 'string', 'max:2000'],
            'fecha_proxima_revision' => ['required', 'date', 'after_or_equal:today'],
        ]);

        \DB::transaction(function () use ($proceso, $data, $request) {
            foreach ($data['documentos'] as $docData) {
                $file = $docData['archivo'];
                $dir  = "procesos/{$proceso->id}";
                $path = $file->store($dir, 'private');

                DocumentoProceso::create([
                    'proceso_radicado_id' => $proceso->id,
                    'user_id'             => $request->user()->id ?? null,
                    'descripcion'         => $docData['nota'] ?? null,
                    'file_name'           => $docData['nombre'],
                    'file_path'           => $path,
                ]);

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

            $proceso->auditoria()->create([
                'user_id' => Auth::id(),
                'evento' => 'ACTUALIZAR_REVISION_PROCESO',
                'descripcion_breve' => "Actualizada próxima revisión a {$data['fecha_proxima_revision']} tras subida masiva de documentos",
                'criticidad' => 'baja',
                'direccion_ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        });

        app(ExpedienteIntegrityService::class)->refresh($proceso->refresh());

        return back()->with('success', 'Documentos cargados y próxima revisión actualizada.');
    }

    public function view(DocumentoProceso $documento)
    {
        $this->authorize('view', $documento->proceso);

        $disk = $this->resolveStorageDisk($documento);
        if (! $disk) {
            abort(404);
        }

        $mime = Storage::disk($disk)->mimeType($documento->file_path) ?: 'application/octet-stream';
        return response(Storage::disk($disk)->get($documento->file_path), 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.addslashes($documento->file_name ?: 'archivo').'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function download(DocumentoProceso $documento)
    {
        $this->authorize('view', $documento->proceso);

        $disk = $this->resolveStorageDisk($documento);
        if (! $disk) {
            abort(404);
        }

        return Storage::disk($disk)->download($documento->file_path, $documento->file_name ?: 'archivo');
    }

    public function destroy(ProcesoRadicado $proceso, DocumentoProceso $documento)
    {
        $this->authorize('update', $proceso);

        if ((int) $documento->proceso_radicado_id !== (int) $proceso->id) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $nombreDoc = $documento->file_name;
        $disk = $this->resolveStorageDisk($documento);

        if ($disk) {
            Storage::disk($disk)->delete($documento->file_path);
        }
        $documento->delete();

        $proceso->auditoria()->create([
            'user_id' => Auth::id(),
            'evento' => 'ELIMINAR_DOCUMENTO_PROCESO',
            'descripcion_breve' => "Eliminado documento '{$nombreDoc}' del radicado {$proceso->radicado}",
            'criticidad' => 'alta',
            'direccion_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        app(ExpedienteIntegrityService::class)->refresh($proceso->refresh());

        return back()->with('success', 'Documento eliminado.');
    }

    private function resolveStorageDisk(DocumentoProceso $documento): ?string
    {
        if (! $documento->file_path) {
            return null;
        }

        foreach (['private', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($documento->file_path)) {
                return $disk;
            }
        }

        return null;
    }
}
