<?php

namespace App\Exports;

use App\Models\IntegracionExternaLog;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class IntegracionesLogExport implements FromQuery, WithHeadings, WithMapping
{
    protected $filters;

    // El constructor recibe los filtros que el usuario aplicó en la pantalla.
    public function __construct(array $filters)
    {
        $this->filters = $filters;
    }

    /**
     * Define los encabezados de las columnas en el archivo de Excel.
     */
    public function headings(): array
    {
        return [
            'ID',
            'Servicio',
            'Endpoint',
            'Fecha Solicitud',
            'ID Usuario',
            'Datos Enviados',
            'Respuesta Recibida',
        ];
    }

    /**
     * Prepara cada fila del Excel.
     * Aquí transformamos los datos para que se vean bien.
     */
    public function map($log): array
    {
        return [
            $log->id,
            $log->servicio,
            $log->endpoint,
            $log->fecha_solicitud,
            $log->user_id ?? 'N/A',
            $log->datos_enviados,
            $log->respuesta,
        ];
    }

    /**
     * Esta es la consulta a la base de datos.
     * ¡Usa exactamente la misma lógica de filtros que nuestro controlador!
     */
    public function query()
    {
        $query = IntegracionExternaLog::query()
            ->when($this->filters['servicio'] ?? null, function ($q, $servicio) {
                $q->where('servicio', 'like', '%' . $servicio . '%');
            })
            ->when($this->filters['fecha_desde'] ?? null, function ($q, $fecha_desde) {
                $q->whereDate('fecha_solicitud', '>=', $fecha_desde);
            })
            ->when($this->filters['fecha_hasta'] ?? null, function ($q, $fecha_hasta) {
                $q->whereDate('fecha_solicitud', '<=', $fecha_hasta);
            });

        return $query->latest();
    }
}
