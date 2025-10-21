<?php

namespace App\Exports;

use App\Models\Caso;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class CasosReportExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    protected $query;

    public function __construct($query)
    {
        $this->query = $query;
    }

    /**
    * @return \Illuminate\Database\Query\Builder
    */
    public function query()
    {
        return $this->query;
    }

    /**
     * Define los encabezados de las columnas en el Excel.
     */
    public function headings(): array
    {
        return [
            'ID Caso',
            'Cooperativa',
            'Deudor',
            'Cédula Deudor',
            'Monto Total Deuda',
            'Fecha Vencimiento',
            'Días en Mora',
            'Estado del Proceso',
            'Gestor Asignado',
        ];
    }

    /**
     * Mapea los datos de cada caso a las columnas del Excel.
     * @param mixed $caso
     */
    public function map($caso): array
    {
        return [
            $caso->id,
            $caso->cooperativa->nombre ?? 'N/A',
            $caso->deudor->nombre_completo ?? 'N/A',
            $caso->deudor->numero_documento ?? 'N/A',
            $caso->monto_total,
            $caso->fecha_vencimiento,
            $caso->dias_en_mora,
            $caso->estado_proceso,
            $caso->user->name ?? 'N/A',
        ];
    }
}
