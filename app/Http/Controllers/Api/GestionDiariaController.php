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

        $keywords = explode(' ', $term);
        $query = Juzgado::query();

        foreach ($keywords as $keyword) {
            if (empty($keyword)) continue;
            $normalized = $this->normalizeTerm($keyword);
            $query->whereRaw("TRANSLATE(nombre, 'áéíóúüÁÉÍÓÚÜ', 'aeiouuAEIOUU') ILIKE ?", ["%{$normalized}%"]);
        }
        
        return $query->limit(15)->get(['id', 'nombre']);
    }

    public function searchVinculacion(Request $request)
    {
        $type = $request->type;
        $term = $request->q;
        if (strlen($term) < 2 || !$type) return [];

        $keywords = explode(' ', $term);

        if ($type === 'App\Models\Caso') {
            $query = Caso::with('deudor');
            foreach ($keywords as $keyword) {
                if (empty($keyword)) continue;
                $norm = $this->normalizeTerm($keyword);
                $query->where(function($q) use ($keyword, $norm) {
                    $q->where('referencia_credito', 'ilike', "%{$keyword}%")
                      ->orWhere('radicado', 'ilike', "%{$keyword}%")
                      ->orWhereRaw("TRANSLATE(referencia_credito, 'áéíóúüÁÉÍÓÚÜ', 'aeiouuAEIOUU') ILIKE ?", ["%{$norm}%"])
                      ->orWhereHas('deudor', function($sq) use ($keyword, $norm) {
                          $sq->where('numero_documento', 'like', "%{$keyword}%")
                            ->orWhereRaw("TRANSLATE(nombre_completo, 'áéíóúüÁÉÍÓÚÜ', 'aeiouuAEIOUU') ILIKE ?", ["%{$norm}%"]);
                      });
                });
            }
            return $query->limit(15)->get();
        } 
        elseif ($type === 'App\Models\ProcesoRadicado') {
            $query = ProcesoRadicado::with(['demandantes', 'demandados']);
            foreach ($keywords as $keyword) {
                if (empty($keyword)) continue;
                $norm = $this->normalizeTerm($keyword);
                $query->where(function($q) use ($keyword, $norm) {
                    $q->where('radicado', 'ilike', "%{$keyword}%")
                      ->orWhereRaw("TRANSLATE(asunto, 'áéíóúüÁÉÍÓÚÜ', 'aeiouuAEIOUU') ILIKE ?", ["%{$norm}%"])
                      ->orWhereHas('demandantes', function($sq) use ($keyword, $norm) {
                          $sq->where('numero_documento', 'like', "%{$keyword}%")
                            ->orWhereRaw("TRANSLATE(nombre_completo, 'áéíóúüÁÉÍÓÚÜ', 'aeiouuAEIOUU') ILIKE ?", ["%{$norm}%"]);
                      })
                      ->orWhereHas('demandados', function($sq) use ($keyword, $norm) {
                          $sq->where('numero_documento', 'like', "%{$keyword}%")
                            ->orWhereRaw("TRANSLATE(nombre_completo, 'áéíóúüÁÉÍÓÚÜ', 'aeiouuAEIOUU') ILIKE ?", ["%{$norm}%"]);
                      });
                });
            }
            return $query->limit(15)->get();
        }
        elseif ($type === 'App\Models\Contrato') {
            $query = Contrato::with('cliente');
            foreach ($keywords as $keyword) {
                if (empty($keyword)) continue;
                $norm = $this->normalizeTerm($keyword);
                $query->where(function($q) use ($keyword, $norm) {
                    $q->where('referencia', 'ilike', "%{$keyword}%")
                      ->orWhereHas('cliente', function($sq) use ($keyword, $norm) {
                          $sq->where('numero_documento', 'like', "%{$keyword}%")
                            ->orWhereRaw("TRANSLATE(nombre_completo, 'áéíóúüÁÉÍÓÚÜ', 'aeiouuAEIOUU') ILIKE ?", ["%{$norm}%"]);
                      });
                });
            }
            return $query->limit(15)->get();
        }

        return [];
    }

    private function normalizeTerm($term)
    {
        return str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ü', 'Á', 'É', 'Í', 'Ó', 'Ú', 'Ü'],
            ['a', 'e', 'i', 'o', 'u', 'u', 'A', 'E', 'I', 'O', 'U', 'U'],
            $term
        );
    }
}
