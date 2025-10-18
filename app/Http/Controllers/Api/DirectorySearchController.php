<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cooperativa;
use App\Models\User;
use App\Models\Persona;
use App\Models\TipoProceso;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DirectorySearchController extends Controller
{
    /**
     * Busca cooperativas por nombre.
     */
    public function cooperativas(Request $request): JsonResponse
    {
        $query = $request->query('q', $request->query('term', ''));

        if (strlen($query) < 3) {
            return response()->json([]);
        }

        $results = Cooperativa::where('nombre', 'ILIKE', "%{$query}%")
            ->select('id', 'nombre as label')
            ->limit(10)
            ->get();

        return response()->json($results);
    }

    /**
     * Busca usuarios con rol de 'abogado' o 'gestor' por nombre.
     */
    public function usuariosAbogadosYGestores(Request $request): JsonResponse
    {
        $query = $request->query('q', $request->query('term', ''));

        if (strlen($query) < 3) {
            return response()->json([]);
        }

        // ===== INICIO DE LA CORRECCIÓN FINAL =====
        // Se ajusta la consulta para usar la columna correcta 'tipo_usuario'
        // según la estructura de la base de datos.
        $results = User::whereIn('tipo_usuario', ['abogado', 'gestor'])
            ->where('name', 'ILIKE', "%{$query}%")
            ->select('id', 'name as label')
            ->limit(10)
            ->get();
        // ===== FIN DE LA CORRECCIÓN FINAL =====

        return response()->json($results);
    }

    /**
     * Busca personas por nombre o número de documento.
     */
    public function personas(Request $request): JsonResponse
    {
        $query = $request->query('q', $request->query('term', ''));

        if (strlen($query) < 3) {
            return response()->json([]);
        }

        $results = Persona::where(function ($q) use ($query) {
                $q->where('nombre_completo', 'ILIKE', "%{$query}%")
                    ->orWhere('numero_documento', 'ILIKE', "%{$query}%");
            })
            ->select('id', 'nombre_completo', 'numero_documento')
            ->limit(10)
            ->get()
            ->map(function ($persona) {
                return [
                    'id' => $persona->id,
                    'label' => "{$persona->nombre_completo} ({$persona->numero_documento})",
                    'nombre_completo' => $persona->nombre_completo,
                    'numero_documento' => $persona->numero_documento,
                ];
            });

        return response()->json($results);
    }

    /**
     * Busca tipos de proceso por nombre.
     */
    public function tiposProceso(Request $request): JsonResponse
    {
        $query = $request->query('q', $request->query('term', ''));

        if (strlen($query) < 2) { // Puedes ajustar el mínimo si lo deseas
            return response()->json([]);
        }

        $results = TipoProceso::where('nombre', 'ilike', "%{$query}%")
            ->select('id', 'nombre as label') // 'label' es lo que AsyncSelect muestra por defecto
            ->limit(10)
            ->get();

        return response()->json($results);
    }
}

