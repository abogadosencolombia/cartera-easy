<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class TipoProcesoSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim($request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        // La tabla en tu BD se llama "tipos_proceso"
        $items = DB::table('tipos_proceso')
            ->when($q, fn ($qq) => $qq->where('nombre', 'ilike', "%{$q}%"))
            ->orderBy('nombre')
            ->limit(50)
            ->get(['id', 'nombre']);

        return response()->json($items);
    }
}
