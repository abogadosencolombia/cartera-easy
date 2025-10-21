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

        // Mantenemos tu validación, ¡es una buena práctica!
        if (strlen($searchTerm) < 3) {
            return response()->json([]);
        }

        // =======================================================
        // ===== INICIO DEL CAMBIO: BÚSQUEDA POR PALABRAS CLAVE ====
        // =======================================================

        // 1. Dividir el término de búsqueda en palabras individuales.
        $keywords = explode(' ', $searchTerm);

        // 2. Construir la consulta dinámicamente.
        $query = Juzgado::query();

        foreach ($keywords as $keyword) {
            // Se asegura de que la palabra no esté vacía antes de añadir la condición.
            if (!empty($keyword)) {
                $query->where('nombre', 'ILIKE', '%' . $keyword . '%');
            }
        }

        // 3. Ejecutar la consulta con el límite y la selección de columnas que ya tenías.
        $juzgados = $query
            ->limit(50)
            ->get(['id', 'nombre']);

        // =======================================================
        // ===== FIN DEL CAMBIO ==================================
        // =======================================================

        // 👇 Mantenemos tu excelente normalización para el frontend.
        // Esto no necesita cambiar en absoluto.
        return response()->json(
            $juzgados->map(fn ($j) => [
                'id'    => $j->id,
                'label' => $j->nombre,
                'value' => $j->nombre,
            ])
        );
    }
}
