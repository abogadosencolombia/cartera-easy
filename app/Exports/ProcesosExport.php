<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProcesosExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $query;

    public function __construct($query)
    {
        $this->query = $query;
    }

    public function query()
    {
        return $this->query;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Radicado',
            'Fecha Radicado',
            'Estado',
            'Tipo de Proceso',
            'Juzgado / Entidad',
            'Demandantes (Nombre - Documento)',
            'Demandados (Nombre - Documento)',
            'Abogado / Gestor',
            'Responsable Revisión',
            'Asunto',
            'Naturaleza',
            'Próxima Revisión',
            'Última Actuación (Sistema)',
            'Link Expediente',
            'Ubicación Drive',
            'Correo Radicación',
            'Correos Juzgado',
            'Observaciones',
            'Nota de Cierre',
            'Fecha Creación',
        ];
    }

    public function map($proceso): array
    {
        // Helper para formatear listas de personas
        $formatPersonas = function($personas) {
            if ($personas->isEmpty()) return 'N/A';
            return $personas->map(function($p) {
                return $p->nombre_completo . ' (' . ($p->numero_documento ?? 'S/D') . ')';
            })->implode('; '); // Separados por punto y coma
        };

        return [
            $proceso->id,
            $proceso->radicado,
            $proceso->fecha_radicado ? $proceso->fecha_radicado->format('Y-m-d') : '',
            $proceso->estado,
            $proceso->tipoProceso->nombre ?? '',
            $proceso->juzgado->nombre ?? '',
            
            // Campos concatenados
            $formatPersonas($proceso->demandantes),
            $formatPersonas($proceso->demandados),
            
            $proceso->abogado->name ?? '',
            $proceso->responsableRevision->name ?? '',
            $proceso->asunto,
            $proceso->naturaleza,
            $proceso->fecha_proxima_revision ? $proceso->fecha_proxima_revision->format('Y-m-d') : '',
            $proceso->ultima_actuacion,
            $proceso->link_expediente,
            $proceso->ubicacion_drive,
            $proceso->correo_radicacion,
            $proceso->correos_juzgado,
            $proceso->observaciones,
            $proceso->nota_cierre,
            $proceso->created_at->format('Y-m-d H:i'),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Negrita para la primera fila (encabezados)
            1 => ['font' => ['bold' => true]],
        ];
    }
}