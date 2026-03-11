<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotaGestion;
use App\Models\NotaGestionArchivo;
use App\Models\Caso;
use App\Models\ProcesoRadicado;
use App\Models\Contrato;
use App\Models\Juzgado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class GestionDiariaController extends Controller
{
    public function index()
    {
        return NotaGestion::where('user_id', Auth::id())
            ->with(['relacionable', 'archivos'])
            ->orderBy('is_completed', 'asc')
            ->orderBy('expires_at', 'asc')
            ->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'descripcion' => 'required|min:5',
            'despacho' => 'required',
            'termino' => 'required',
            'archivos.*' => 'nullable|file|max:10240', // 20MB por archivo
        ]);

        $nota = NotaGestion::create([
            'user_id' => Auth::id(),
            'descripcion' => strtoupper($request->descripcion),
            'despacho' => strtoupper($request->despacho),
            'termino' => strtoupper($request->termino),
            'relacionable_type' => $request->relacionable_type ?: null,
            'relacionable_id' => $request->relacionable_id ?: null,
            'expires_at' => now()->addHours(8),
        ]);

        if ($request->hasFile('archivos')) {
            foreach ($request->file('archivos') as $file) {
                $path = $file->store("gestion_diaria/{$nota->id}", 'public');
                $nota->archivos()->create([
                    'nombre_original' => $file->getClientOriginalName(),
                    'ruta_archivo' => $path,
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'uploaded_by' => Auth::id(),
                ]);
            }
        }

        return response()->json($nota->load(['relacionable', 'archivos']), 201);
    }

    public function complete($id)
    {
        $nota = NotaGestion::where('user_id', Auth::id())->findOrFail($id);
        $nota->update(['is_completed' => true, 'completed_at' => now()]);
        return response()->json($nota);
    }

    public function destroy($id)
    {
        $nota = NotaGestion::where('user_id', Auth::id())->where('is_completed', true)->findOrFail($id);
        
        foreach ($nota->archivos as $archivo) {
            Storage::disk('public')->delete($archivo->ruta_archivo);
        }
        
        $nota->delete();
        return response()->json(['message' => 'Eliminado']);
    }

    public function downloadArchivo($id)
    {
        $archivo = NotaGestionArchivo::whereHas('notaGestion', function($q) {
            $q->where('user_id', Auth::id());
        })->findOrFail($id);

        return Storage::disk('public')->download($archivo->ruta_archivo, $archivo->nombre_original);
    }

    public function viewArchivo($id)
    {
        $archivo = NotaGestionArchivo::whereHas('notaGestion', function($q) {
            $q->where('user_id', Auth::id());
        })->findOrFail($id);

        return Storage::disk('public')->response($archivo->ruta_archivo);
    }

    public function searchDespacho(Request $request)
    {
        $term = $request->q;
        if (strlen($term) < 2) return [];
        
        return Juzgado::where('nombre', 'ilike', "%{$term}%")
            ->limit(10)
            ->get(['id', 'nombre']);
    }

    public function searchVinculacion(Request $request)
    {
        $type = $request->type;
        $term = $request->q;
        if (strlen($term) < 2 || !$type) return [];

        if ($type === 'App\Models\Caso') {
            return Caso::with('deudor')
                ->where(function($q) use ($term) {
                    $q->where('referencia_credito', 'ilike', "%{$term}%")
                      ->orWhere('radicado', 'ilike', "%{$term}%")
                      ->orWhereHas('deudor', function($sq) use ($term) {
                          $sq->where('nombre_completo', 'ilike', "%{$term}%")
                            ->orWhere('numero_documento', 'like', "%{$term}%"); // Mantenemos like para números
                      });
                })
                ->limit(10)->get();
        } 
        elseif ($type === 'App\Models\ProcesoRadicado') {
            return ProcesoRadicado::with(['demandantes', 'demandados'])
                ->where(function($q) use ($term) {
                    $q->where('radicado', 'ilike', "%{$term}%")
                      ->orWhere('asunto', 'ilike', "%{$term}%")
                      ->orWhereHas('demandantes', function($sq) use ($term) {
                          $sq->where('nombre_completo', 'ilike', "%{$term}%")
                            ->orWhere('numero_documento', 'like', "%{$term}%");
                      })
                      ->orWhereHas('demandados', function($sq) use ($term) {
                          $sq->where('nombre_completo', 'ilike', "%{$term}%")
                            ->orWhere('numero_documento', 'like', "%{$term}%");
                      });
                })
                ->limit(10)->get();
        }
        elseif ($type === 'App\Models\Contrato') {
            return Contrato::with('cliente')
                ->where('referencia', 'ilike', "%{$term}%")
                ->orWhereHas('cliente', function($q) use ($term) {
                    $q->where('nombre_completo', 'ilike', "%{$term}%")
                      ->orWhere('numero_documento', 'like', "%{$term}%");
                })->limit(10)->get();
        }

        return [];
    }
}
