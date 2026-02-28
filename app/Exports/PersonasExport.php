<?php

namespace App\Exports;

use App\Models\Persona;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\Exportable;
use Illuminate\Database\Eloquent\Builder;

class PersonasExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    use Exportable;

    protected $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function query(): Builder
    {
        $filters = $this->filters;
        $query = Persona::query()->with(['cooperativas:id,nombre', 'abogados:id,name']);

        // 1. Estado
        if (isset($filters['status']) && $filters['status'] === 'suspended') {
            $query->onlyTrashed();
        } else {
            $query->withoutTrashed();
        }

        // 2. Búsqueda
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nombre_completo', 'ilike', "%{$search}%")
                  ->orWhere('numero_documento', 'ilike', "%{$search}%")
                  ->orWhere('id', 'ilike', "%{$search}%");
            });
        }
        
        // 3. Cooperativa
        if (!empty($filters['cooperativa_id'])) {
            $query->whereHas('cooperativas', fn($sq) => $sq->where('cooperativas.id', $filters['cooperativa_id']));
        }
        
        // 4. Abogado
        if (!empty($filters['abogado_id'])) {
            $query->whereHas('abogados', fn($sq) => $sq->where('users.id', $filters['abogado_id']));
        }

        // 5. NUEVO: Por Tipo (Deudor / Demandado)
        if (!empty($filters['tipo_rol'])) {
            if ($filters['tipo_rol'] === 'demandado') {
                $query->where('es_demandado', true);
            } elseif ($filters['tipo_rol'] === 'deudor') {
                $query->where('es_demandado', false);
            }
        }

        // 6. Orden
        $sort = $filters['sort'] ?? 'created_at';
        $direction = $filters['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        return $query;
    }

    public function headings(): array
    {
        return [
            'ID', 'Nombre Completo', 'Tipo Rol', 'Tipo Documento', 'Número Documento', 
            'Fecha Expedición', 'Celular', 'Correo', 'Empresa', 'Cargo', 
            'Direcciones', 'Cooperativas', 'Abogados', 'Estado'
        ];
    }

    public function map($persona): array
    {
        $mapArray = function($array, $labelKey, $valueKey) {
            if (empty($array)) return '';
            return collect($array)->map(fn($i) => ($i[$labelKey] ?? 'Dir') . ': ' . ($i[$valueKey] ?? ''))->join(' | ');
        };

        return [
            $persona->id,
            $persona->nombre_completo,
            $persona->es_demandado ? 'DEMANDADO' : 'DEUDOR/CLIENTE',
            $persona->tipo_documento,
            $persona->numero_documento,
            $persona->fecha_expedicion,
            $persona->celular_1,
            $persona->correo_1,
            $persona->empresa,
            $persona->cargo,
            $mapArray($persona->addresses, 'label', 'address'),
            $persona->cooperativas->pluck('nombre')->join(', '),
            $persona->abogados->pluck('name')->join(', '),
            $persona->deleted_at ? 'Suspendido' : 'Activo',
        ];
    }
}
