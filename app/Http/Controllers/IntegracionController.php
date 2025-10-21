<?php

namespace App\Http\Controllers;

use App\Models\IntegracionExternaLog;
use Illuminate\Http\Request; // <-- AÑADIDO: Para poder leer los filtros de la URL.
use Inertia\Inertia;
use App\Exports\IntegracionesLogExport;
use Maatwebsite\Excel\Facades\Excel;

class IntegracionController extends Controller
{
    public function index(Request $request) // <-- AÑADIDO: Inyectamos el objeto Request.
    {
        // Obtenemos los filtros de la URL. Si no existen, usamos un array vacío.
        $filters = $request->only(['servicio', 'fecha_desde', 'fecha_hasta']);

        // Empezamos la consulta a la base de datos.
        $logs = IntegracionExternaLog::query()
            // El método when() es un condicional: solo aplica el filtro si el valor existe.
            // Es una forma muy limpia de construir consultas dinámicas.

            // Filtro por nombre de servicio
            ->when($filters['servicio'] ?? null, function ($query, $servicio) {
                $query->where('servicio', 'ilike', '%' . $servicio . '%');
            })

            // Filtro por fecha "desde"
            ->when($filters['fecha_desde'] ?? null, function ($query, $fecha_desde) {
                $query->whereDate('fecha_solicitud', '>=', $fecha_desde);
            })

            // Filtro por fecha "hasta"
            ->when($filters['fecha_hasta'] ?? null, function ($query, $fecha_hasta) {
                $query->whereDate('fecha_solicitud', '<=', $fecha_hasta);
            })

            // Finalmente, ordenamos y paginamos el resultado.
            ->latest()
            ->paginate(15)
            // withQueryString() es importante para que la paginación recuerde los filtros aplicados.
            ->withQueryString();

        return Inertia::render('Integraciones/Index', [
            'logs' => $logs,
            'filters' => $filters, // <-- AÑADIDO: Devolvemos los filtros a la vista.
        ]);
    }

    public function export(Request $request)
    {
        $filters = $request->only(['servicio', 'fecha_desde', 'fecha_hasta']);
        $fileName = 'reporte-integraciones-' . date('Y-m-d') . '.xlsx';

        return Excel::download(new IntegracionesLogExport($filters), $fileName);
    }
}