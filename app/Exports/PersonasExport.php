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

    // Recibimos los filtros desde el controlador
    public function __construct(array $filters)
    {
        $this->filters = $filters;
    }

    /**
    * @return \Illuminate\Database\Eloquent\Builder
    */
    public function query(): Builder
    {
        $filters = $this->filters;
        
        // 1. Empezamos la consulta (igual que en PersonaController)
        $query = Persona::query()->with([
            'cooperativas:id,nombre',
            'abogados:id,name'
        ]);

        // 2. Aplicamos el filtro de ESTADO (Activos / Suspendidos)
        if (isset($filters['status']) && $filters['status'] === 'suspended') {
            $query->onlyTrashed();
        } else {
            // Por defecto, solo exporta Activos (igual que la vista)
            $query->withoutTrashed();
        }

        // 3. Aplicamos el filtro de BÚSQUEDA (search)
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nombre_completo', 'ilike', "%{$search}%")
                  ->orWhere('numero_documento', 'ilike', "%{$search}%")
                  ->orWhere('celular_1', 'ilike', "%{$search}%")
                  ->orWhere('correo_1', 'ilike', "%{$search}%")
                  ->orWhereRaw('CAST(id AS TEXT) Ilike ?', ["%{$search}%"]);
            });
        }
        
        // 4. Aplicamos el filtro de COOPERATIVA
        if (!empty($filters['cooperativa_id'])) {
            $query->whereHas('cooperativas', fn($sq) => $sq->where('cooperativas.id', $filters['cooperativa_id']));
        }
        
        // 5. Aplicamos el filtro de ABOGADO
        if (!empty($filters['abogado_id'])) {
            $query->whereHas('abogados', fn($sq) => $sq->where('users.id', $filters['abogado_id']));
        }

        // 6. Aplicamos el ORDEN
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';
        $validSortColumns = ['nombre_completo', 'created_at'];
        
        if (!in_array($sortBy, $validSortColumns)) {
            $sortBy = 'created_at';
        }
        
        $query->orderBy($sortBy, $sortDirection);

        return $query;
    }

    /**
    * @return array
    */
    public function headings(): array
    {
        // Estos son los títulos de las columnas en el Excel
        return [
            'ID',
            'Nombre Completo',
            'Tipo Documento',
            'Número Documento',
            'Fecha Expedición',
            'Celular Principal',
            'Celular Secundario',
            'Correo Principal',
            'Correo Secundario',
            'Teléfono Fijo',
            'Empresa',
            'Cargo',
            'Observaciones',
            'Direcciones',
            'Redes Sociales',
            'Cooperativas Asignadas',
            'Abogados Asignados',
            'Estado',
        ];
    }

    /**
    * @param Persona $persona
    * @return array
    */
    public function map($persona): array
    {
        // Esta función formatea cada fila del Excel

        // Helper para convertir arrays (direcciones, redes) en texto
        $mapArray = function($array, $labelKey, $valueKey) {
            if (empty($array)) return '';
            return collect($array)->map(function($item) use ($labelKey, $valueKey) {
                $label = $item[$labelKey] ?? '';
                $value = $item[$valueKey] ?? '';
                // Evita mostrar "null: null" si un campo está vacío
                if(empty($label) && empty($value)) return null;
                return trim("$label: $value");
            })->filter()->join(' | '); // "filter()" elimina valores nulos
        };

        $addresses = $mapArray($persona->addresses, 'label', 'address');
        $socials = $mapArray($persona->social_links, 'label', 'url');
        
        $cooperativas = $persona->cooperativas->isEmpty() 
            ? '' 
            : $persona->cooperativas->pluck('nombre')->join(', ');
            
        $abogados = $persona->abogados->isEmpty() 
            ? '' 
            : $persona->abogados->pluck('name')->join(', ');

        // Este es el orden final de los datos en cada fila
        return [
            $persona->id,
            $persona->nombre_completo,
            $persona->tipo_documento,
            $persona->numero_documento,
            $persona->fecha_expedicion, // Tu modelo ya lo formatea 'Y-m-d'
            $persona->celular_1,
            $persona->celular_2,
            $persona->correo_1,
            $persona->correo_2,
            $persona->telefono_fijo,
            $persona->empresa,
            $persona->cargo,
            $persona->observaciones,
            $addresses,
            $socials,
            $cooperativas,
            $abogados,
            $persona->deleted_at ? 'Suspendido' : 'Activo',
        ];
    }
}
