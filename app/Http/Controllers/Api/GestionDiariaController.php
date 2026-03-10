<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotaGestion;
use App\Models\Caso;
use App\Models\ProcesoRadicado;
use App\Models\Contrato;
use App\Models\Juzgado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GestionDiariaController extends Controller
{
    public function index()
    {
        return NotaGestion::where('user_id', Auth::id())
            ->with('relacionable')
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

        return response()->json($nota->load('relacionable'), 201);
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
        $nota->delete();
        return response()->json(['message' => 'Eliminado']);
    }

    public function searchDespacho(Request $request)
    {
        $term = strtolower($request->q);
        if (strlen($term) < 2) return [];
        
        return Juzgado::whereRaw('LOWER(nombre) like ?', ["%{$term}%"])
            ->limit(10)
            ->get(['id', 'nombre']);
    }

    public function searchVinculacion(Request $request)
    {
        $type = $request->type;
        $term = strtolower($request->q);
        if (strlen($term) < 2 || !$type) return [];

        if ($type === 'App\Models\Caso') {
            return Caso::whereRaw('LOWER(referencia_credito) like ?', ["%{$term}%"])
                ->orWhereRaw('LOWER(radicado) like ?', ["%{$term}%"])
                ->limit(10)->get();
        } 
        elseif ($type === 'App\Models\ProcesoRadicado') {
            return ProcesoRadicado::whereRaw('LOWER(radicado) like ?', ["%{$term}%"])
                ->orWhereRaw('LOWER(asunto) like ?', ["%{$term}%"])
                ->limit(10)->get();
        }
        elseif ($type === 'App\Models\Contrato') {
            return Contrato::with('cliente')
                ->whereRaw('LOWER(referencia) like ?', ["%{$term}%"])
                ->orWhereHas('cliente', function($q) use ($term) {
                    $q->whereRaw('LOWER(nombre_completo) like ?', ["%{$term}%"]);
                })->limit(10)->get();
        }

        return [];
    }
}
