<?php

namespace App\Exports;

use App\Models\Persona;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\Exportable;
use Illuminate\Database\Eloquent\Builder;

use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;

class PersonasExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    use Exportable;

    protected $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function styles(Worksheet $sheet)
    {
        // Estilo para el encabezado
        $sheet->getStyle('1')->getFont()->setBold(true)->setColor(new Color(Color::COLOR_WHITE));
        $sheet->getStyle('1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF4F46E5'); // Indigo 600
        $sheet->getStyle('1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Estilos generales
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();
        $range = 'A1:' . $highestColumn . $highestRow;

        $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFD1D5DB'); // Gray 300

        // Ajuste de columnas específicas
        $sheet->getStyle('K')->getAlignment()->setWrapText(true);
        $sheet->getColumnDimension('K')->setWidth(50); // Direcciones
        $sheet->getColumnDimension('B')->setWidth(35); // Nombre completo
        
        // Alternar colores de filas (Zebra)
        for ($i = 2; $i <= $highestRow; $i++) {
            if ($i % 2 == 0) {
                $sheet->getStyle('A' . $i . ':' . $highestColumn . $highestRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF9FAFB');
            }
        }

        return [
            1    => ['font' => ['size' => 12]],
        ];
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
        $sort = $filters['sort'] ?? 'updated_at';
        $direction = $filters['direction'] ?? 'desc';

        // Validar campos permitidos
        $allowedSorts = ['updated_at', 'created_at', 'nombre_completo', 'id'];
        if (!in_array($sort, $allowedSorts)) {
            $sort = 'updated_at';
        }

        // Validar dirección
        $direction = (strtolower($direction) === 'asc') ? 'asc' : 'desc';

        $query->orderBy("personas.{$sort}", $direction);

        // Si es por fecha, añadir ID para consistencia
        if (in_array($sort, ['updated_at', 'created_at'])) {
            $query->orderBy('personas.id', $direction);
        }

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
