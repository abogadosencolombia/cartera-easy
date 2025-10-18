<?php

namespace App\Exports;

use App\Models\IncidenteJuridico;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class IncidentesExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    protected $filters;

    public function __construct(array $filters)
    {
        $this->filters = $filters;
    }

    public function query()
    {
        $query = IncidenteJuridico::with('responsable:id,name')->latest('fecha_registro');

        $query->when($this->filters['search'] ?? null, function ($query, $search) {
            $query->where('asunto', 'like', "%{$search}%");
        });
        $query->when($this->filters['estado'] ?? null, function ($query, $estado) {
            $query->where('estado', $estado);
        });
        $query->when($this->filters['responsable_id'] ?? null, function ($query, $responsable_id) {
            $query->where('usuario_responsable_id', $responsable_id);
        });

        return $query;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Asunto',
            'Descripción',
            'Origen',
            'Estado',
            'Responsable',
            'Fecha de Registro',
        ];
    }

    public function map($incidente): array
    {
        return [
            $incidente->id,
            $incidente->asunto,
            $incidente->descripcion,
            ucfirst($incidente->origen),
            ucfirst(str_replace('_', ' ', $incidente->estado)),
            $incidente->responsable ? $incidente->responsable->name : 'N/A',
            $incidente->fecha_registro,
        ];
    }
}