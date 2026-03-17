<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Juzgado;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class JuzgadoSearchController extends Controller
{
    /**
     * Maneja la búsqueda de juzgados por palabras clave.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $searchTerm = $request->query('q', '');

        if (strlen($searchTerm) < 2) {
            return response()->json([]);
        }

        $keywords = explode(' ', $searchTerm);
        $query = Juzgado::query();

        foreach ($keywords as $keyword) {
            if (empty($keyword)) continue;
            
            $normalized = $this->normalizeTerm($keyword);
            $query->whereRaw("TRANSLATE(nombre, 'áéíóúüÁÉÍÓÚÜ', 'aeiouuAEIOUU') ILIKE ?", ["%{$normalized}%"]);
        }

        $juzgados = $query
            ->limit(50)
            ->get(['id', 'nombre']);

        return response()->json(
            $juzgados->map(fn ($j) => [
                'id'    => $j->id,
                'label' => $j->nombre,
                'value' => $j->nombre,
            ])
        );
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
