<?php

namespace App\Exports;

use App\Models\ProcesoRadicado;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class ProcesosExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithColumnFormatting
{
    protected $filtros;

    public function __construct($filtros = [])
    {
        $this->filtros = $filtros;
    }

    public function columnFormats(): array
    {
        return [
            'B' => NumberFormat::FORMAT_TEXT,
        ];
    }

    public function query()
    {
        $query = ProcesoRadicado::query()
            ->with([
                'juzgado',
                'abogado',
                'responsableRevision', 
                'demandantes', 
                'demandados',
                'creator',
                'tipoProceso',
                'etapaActual'
            ])
            ->latest();

        // 1. Filtro de Búsqueda
        if (!empty($this->filtros['search'])) {
            $search = $this->filtros['search'];
            $query->where(function ($q) use ($search) {
                $q->where('radicado', 'ilike', "%{$search}%")
                    ->orWhere('asunto', 'ilike', "%{$search}%")
                    ->orWhereHas('demandantes', function($sq) use ($search) {
                        $sq->where('nombre_completo', 'Ilike', "%{$search}%")
                           ->orWhere('numero_documento', 'Ilike', "%{$search}%");
                    })
                    ->orWhereHas('demandados', function($sq) use ($search) {
                        $sq->where('nombre_completo', 'Ilike', "%{$search}%")
                           ->orWhere('numero_documento', 'Ilike', "%{$search}%");
                    })
                    ->orWhereHas('abogado', fn($sq) => $sq->where('name', 'Ilike', "%{$search}%"))
                    ->orWhereHas('juzgado', fn($sq) => $sq->where('nombre', 'Ilike', "%{$search}%"));
            });
        }

        // 2. Filtro de Estado
        if (!empty($this->filtros['estado']) && $this->filtros['estado'] !== 'TODOS') {
            $query->where('estado', $this->filtros['estado']);
        }

        // 3. ✅ FILTRO DE TIPO DE ENTIDAD (Juzgados, Fiscalías, etc.)
        if (!empty($this->filtros['tipo_entidad'])) {
            $tipo = $this->filtros['tipo_entidad'];
            $query->whereHas('juzgado', function ($q) use ($tipo) {
                $q->where('nombre', 'ilike', "%{$tipo}%");
            });
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'ID Interno',
            'Radicado Oficial',
            'Fecha Radicación',
            'Estado',
            'Etapa Procesal Actual',
            'Naturaleza',
            'Tipo de Proceso',
            'Asunto / Descripción',

            // --- PARTES INVOLUCRADAS ---
            'Demandantes / Accionantes (Detalle Completo)',
            'Demandados / Accionados (Detalle Completo)',
            
            'Abogado Gestor',
            'Responsable de Revisión',
            'Registrado Por',
            
            'Juzgado / Entidad',
            'Correos del Juzgado',
            
            'Fecha Última Revisión',
            'Fecha Próxima Revisión',
            'Fecha Cambio Etapa',
            'Última Actuación Registrada',
            'Observaciones Generales',
            
            'Link Expediente Digital',
            'Ubicación en Drive',
            'Correo de Radicación',
            
            'Nota de Cierre',
            'Fecha Creación Sistema',
            'Fecha Última Actualización'
        ];
    }

    public function map($proceso): array
    {
        // --- FORMATEO INTELIGENTE DE PERSONAS ---
        $formatoPersona = function($persona) {
            $detalles = [];
            
            // 1. Identificación
            $tipoDoc = $persona->tipo_documento ?? '';
            $numDoc = $persona->numero_documento ?? '';
            $detalles[] = "ID: {$tipoDoc} {$numDoc}";
            
            // 2. BÚSQUEDA DE TELÉFONOS (Prioridad a tus columnas de BD)
            $telCandidates = [
                $persona->celular_1,  
                $persona->celular_2,  
                $persona->telefono_fijo,
                $persona->celular ?? null, 
                $persona->telefono ?? null, 
                $persona->movil ?? null
            ];
            
            $telEncontrado = null;
            foreach ($telCandidates as $cand) {
                if (!empty($cand)) {
                    $telEncontrado = $cand;
                    break; 
                }
            }
            if ($telEncontrado) $detalles[] = "Tel: {$telEncontrado}";
            
            // 3. BÚSQUEDA DE CORREOS
            $mailCandidates = [
                $persona->correo_1, 
                $persona->correo_2, 
                $persona->email ?? null, 
                $persona->correo ?? null
            ];
            
            $mailEncontrado = null;
            foreach ($mailCandidates as $cand) {
                if (!empty($cand)) {
                    $mailEncontrado = $cand;
                    break;
                }
            }
            if ($mailEncontrado) $detalles[] = "Email: {$mailEncontrado}";
            
            // 4. BÚSQUEDA DE DIRECCIÓN
            $dir = $persona->direccion ?? null; 
            
            if (empty($dir) && !empty($persona->addresses)) {
                $addrs = is_string($persona->addresses) ? json_decode($persona->addresses, true) : $persona->addresses;
                if (is_array($addrs) && !empty($addrs)) {
                    $first = reset($addrs); 
                    if (is_string($first)) {
                        $dir = $first;
                    } elseif (is_array($first)) {
                        $dir = $first['direccion'] ?? $first['address'] ?? $first['calle'] ?? implode(' ', $first);
                    }
                }
            }
            
            if (!empty($dir)) $detalles[] = "Dir: {$dir}";
            
            // 5. Ciudad
            $ciudad = $persona->ciudad ?? $persona->municipio ?? null;
            if (!empty($ciudad)) $detalles[] = "Ciu: {$ciudad}";

            // Verificación final
            if (count($detalles) === 1) {
                $detalles[] = "(Sin datos de contacto visibles)";
            }

            return "• " . strtoupper($persona->nombre_completo) . "\n   └ " . implode(" | ", $detalles);
        };

        // Generamos las listas
        $listaDtes = ($proceso->demandantes && $proceso->demandantes->count() > 0)
            ? $proceso->demandantes->map($formatoPersona)->implode("\n\n")
            : "Sin demandantes registrados";

        $listaDdos = ($proceso->demandados && $proceso->demandados->count() > 0)
            ? $proceso->demandados->map($formatoPersona)->implode("\n\n")
            : "Sin demandados registrados";

        // Manejo seguro de responsables
        $nombreAbogado = $proceso->abogado ? $proceso->abogado->name : 'Sin asignar';
        
        $nombreResponsable = 'Sin asignar';
        if ($proceso->relationLoaded('responsableRevision') && $proceso->responsableRevision) {
            $nombreResponsable = $proceso->responsableRevision->name;
        } elseif ($proceso->responsable) { 
            $nombreResponsable = $proceso->responsable->name;
        }
        
        $nombreCreador = $proceso->creator ? $proceso->creator->name : 'Sistema';

        return [
            $proceso->id,
            (string) $proceso->radicado,
            $this->formatDate($proceso->fecha_radicado),
            $proceso->estado ?? 'ACTIVO',
            $proceso->etapaActual ? $proceso->etapaActual->nombre : 'Inicial',
            strtoupper($proceso->naturaleza),
            $proceso->tipoProceso ? $proceso->tipoProceso->nombre : 'General',
            $proceso->asunto,

            $listaDtes,
            $listaDdos,

            $nombreAbogado,
            $nombreResponsable,
            $nombreCreador,

            $proceso->juzgado ? $proceso->juzgado->nombre : 'No registrado',
            $proceso->correos_juzgado,

            $this->formatDate($proceso->fecha_revision),
            $this->formatDate($proceso->fecha_proxima_revision),
            $proceso->fecha_cambio_etapa ? $proceso->fecha_cambio_etapa->format('d/m/Y') : '',
            $proceso->ultima_actuacion,
            $proceso->observaciones,

            $proceso->link_expediente,
            $proceso->ubicacion_drive,
            $proceso->correo_radicacion,

            $proceso->nota_cierre,
            $proceso->created_at->format('d/m/Y H:i'),
            $proceso->updated_at->format('d/m/Y H:i'),
        ];
    }

    private function formatDate($date)
    {
        return $date ? \Carbon\Carbon::parse($date)->format('d/m/Y') : '';
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('1')->getFont()->setBold(true);
        $sheet->getStyle('1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFD9EAD3');
        
        $sheet->getStyle('I')->getAlignment()->setWrapText(true);
        $sheet->getStyle('J')->getAlignment()->setWrapText(true);
        $sheet->getStyle('H')->getAlignment()->setWrapText(true);
        $sheet->getStyle('T')->getAlignment()->setWrapText(true);
        
        $sheet->getStyle($sheet->calculateWorksheetDimension())
              ->getAlignment()
              ->setVertical(Alignment::VERTICAL_TOP);
        
        $sheet->getColumnDimension('I')->setWidth(60);
        $sheet->getColumnDimension('J')->setWidth(60);
    }
}