<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Juzgado;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class JuzgadoSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $searchTerm = $request->query('q', '');

        if (strlen($searchTerm) < 3) {
            return response()->json([]);
        }

        $juzgados = Juzgado::where('nombre', 'ILIKE', '%' . $searchTerm . '%')
            ->limit(50)
            ->get(['id', 'nombre']);

        // 👇 Normalizamos para el componente AsyncSelect (label/value)
        return response()->json(
            $juzgados->map(fn ($j) => [
                'id'    => $j->id,
                'label' => $j->nombre,
                'value' => $j->nombre,
            ])
        );
    }
}
