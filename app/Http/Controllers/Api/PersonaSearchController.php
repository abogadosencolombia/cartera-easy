<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Persona;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PersonaSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim($request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $items = Persona::query()
            ->where(function ($w) use ($q) {
                $w->where('nombre_completo', 'ilike', "%{$q}%")
                  ->orWhere('numero_documento', 'ilike', "%{$q}%");
            })
            ->orderBy('nombre_completo')
            ->limit(50)
            ->get([
                'id',
                'nombre_completo as nombre',
                'numero_documento',
            ]);

        return response()->json($items);
    }
}
