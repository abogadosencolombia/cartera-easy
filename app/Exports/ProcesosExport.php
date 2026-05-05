<?php

namespace App\Exports;

use App\Models\ProcesoRadicado;
use App\Models\Juzgado;
use App\Models\TipoProceso;
use App\Models\EtapaProcesal;
use App\Models\User;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

class ProcesosExport implements FromQuery, WithHeadings, WithMapping, WithEvents
{
    protected $filtros;

    public function __construct($filtros = [])
    {
        $this->filtros = $filtros;
    }

    public function query()
    {
        $query = ProcesoRadicado::query()
            ->with(['juzgado', 'abogado', 'responsableRevision', 'demandantes', 'demandados', 'tipoProceso', 'etapaActual', 'creator'])
            ->select('proceso_radicados.*');

        if (!empty($this->filtros['search'])) {
            $search = $this->filtros['search'];
            $query->where(function ($q) use ($search) {
                $q->where('radicado', 'ilike', "%{$search}%")
                    ->orWhere('asunto', 'ilike', "%{$search}%")
                    ->orWhereHas('demandantes', fn($sq) => $sq->where('nombre_completo', 'ilike', "%{$search}%"))
                    ->orWhereHas('demandados', fn($sq) => $sq->where('nombre_completo', 'ilike', "%{$search}%"));
            });
        }
        
        if (!empty($this->filtros['estado']) && $this->filtros['estado'] !== 'TODOS') {
            $query->where('estado', $this->filtros['estado']);
        }

        if (!empty($this->filtros['juzgado_id'])) {
            $query->where('juzgado_id', $this->filtros['juzgado_id']);
        }

        if (!empty($this->filtros['tipo_entidad'])) {
            $tipo = $this->filtros['tipo_entidad'];
            $query->whereHas('juzgado', fn($q) => $q->where('nombre', 'ilike', "%{$tipo}%"));
        }

        $sinRadicado = filter_var($this->filtros['sin_radicado'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($sinRadicado) {
            $query->where(function($q) { $q->whereNull('radicado')->orWhere('radicado', ''); });
        }

        $soloVencidos = filter_var($this->filtros['solo_vencidos'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($soloVencidos) {
            $query->where('fecha_proxima_revision', '<', now()->toDateString())->paraSeguimiento();
        }

        $cerrados = filter_var($this->filtros['cerrados'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($cerrados) {
            $query->cerrados();
        }

        $actualizadosHoy = filter_var($this->filtros['actualizados_hoy'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($actualizadosHoy) {
            $query->whereBetween('updated_at', [now()->startOfDay(), now()->endOfDay()]);
        }

        return $query->latest('updated_at');
    }

    public function headings(): array
    {
        return [
            'ID INTERNO',
            'Radicado',
            'Fecha Radicacion',
            'Naturaleza',
            'A Favor De',
            'Asunto',
            'Tipo de Proceso',
            'Etapa Procesal',
            'Fecha Cambio Etapa',
            'Estado',
            'Viabilidad',
            'Informacion Incompleta',
            'Demandantes',
            'Demandados',
            'Abogado Gestor',
            'Responsable Revision',
            'Entidad Juzgado Nombre',
            'Correos Juzgado',
            'Fecha Ultima Revision',
            'Fecha Proxima Revision',
            'Ultima Actuacion',
            'Observaciones',
            'Link Expediente Digital',
            'Ubicacion Drive',
            'Correo Radicacion',
            'Nota de Cierre',
            'Fecha Registro Sistema',
            'Ultima Actualizacion',
        ];
    }

    public function map($proceso): array
    {
        $formatoPartes = function($personas) {
            return $personas->map(function($p) {
                $doc = $p->numero_documento ? " [ID: {$p->tipo_documento} {$p->numero_documento}]" : "";
                return "{$p->nombre_completo}{$doc}";
            })->implode('; ');
        };

        return [
            $proceso->id,
            $proceso->radicado,
            $proceso->es_spoa_nunc ? 'SI' : 'NO',
            $proceso->fecha_radicado ? $proceso->fecha_radicado->format('Y-m-d') : '',
            $proceso->naturaleza,
            $proceso->a_favor_de,
            $proceso->asunto,
            $proceso->tipoProceso?->nombre,
            $proceso->etapaActual?->nombre,
            $proceso->fecha_cambio_etapa ? $proceso->fecha_cambio_etapa->format('Y-m-d') : '',
            $proceso->estado,
            strtoupper($proceso->viabilidad_estado ?? 'PENDIENTE'),
            $proceso->info_incompleta ? 'SI' : 'NO',
            $formatoPartes($proceso->demandantes),
            $formatoPartes($proceso->demandados),
            $proceso->abogado?->name,
            $proceso->responsableRevision?->name,
            $proceso->juzgado?->nombre,
            $proceso->correos_juzgado,
            $proceso->fecha_revision ? $proceso->fecha_revision->format('Y-m-d') : '',
            $proceso->fecha_proxima_revision ? $proceso->fecha_proxima_revision->format('Y-m-d') : '',
            $proceso->ultima_actuacion,
            $proceso->observaciones,
            $proceso->link_expediente,
            $proceso->ubicacion_drive,
            $proceso->correo_radicacion,
            $proceso->nota_cierre,
            $proceso->created_at->format('Y-m-d H:i'),
            $proceso->updated_at->format('Y-m-d H:i'),
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();
                $lastCol = $sheet->getHighestColumn();
                $fullRange = "A1:{$lastCol}{$lastRow}";

                // 1. Estilo General y Alineación
                $sheet->getStyle($fullRange)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
                
                // 2. Encabezados Premium
                $headerRange = "A1:{$lastCol}1";
                $sheet->getStyle($headerRange)->getFont()->setBold(true)->setSize(11);
                $sheet->getStyle($headerRange)->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('4F46E5');
                $sheet->getStyle($headerRange)->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE);
                $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

                // 3. Bordes Finos para toda la tabla
                $sheet->getStyle($fullRange)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $sheet->getStyle($fullRange)->getBorders()->getAllBorders()->getColor()->setARGB('E2E8F0');

                // 4. Ajuste de Anchos y Text Wrapping para campos largos
                $longTextFields = [
                    'D' => 20, // Naturaleza
                    'E' => 25, // A Favor De
                    'F' => 45, // Asunto
                    'K' => 15, // Viabilidad
                    'M' => 35, // Demandantes
                    'N' => 35, // Demandados
                    'R' => 30, // Correos Juzgado
                    'U' => 50, // Ultima Actuacion
                    'V' => 45, // Observaciones
                    'W' => 40, // Link
                    'X' => 30, // Drive
                ];

                foreach ($longTextFields as $col => $width) {
                    $sheet->getColumnDimension($col)->setAutoSize(false);
                    $sheet->getColumnDimension($col)->setWidth($width);
                    $sheet->getStyle("{$col}2:{$col}{$lastRow}")->getAlignment()->setWrapText(true);
                }

                // Auto-size para el resto de columnas
                foreach(range('A','Z') as $col) {
                    if (!isset($longTextFields[$col])) {
                        $sheet->getColumnDimension($col)->setAutoSize(true);
                    }
                }
                $sheet->getColumnDimension('AA')->setAutoSize(true);
                $sheet->getColumnDimension('AB')->setAutoSize(true);

                // 5. Cebra / Rayado de filas para lectura fácil
                for ($row = 2; $row <= $lastRow; $row++) {
                    if ($row % 2 == 0) {
                        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()
                            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                            ->getStartColor()->setARGB('F8FAFC');
                    }
                }

                // 6. Validaciones de Datos (Hoja Oculta)
                $spreadsheet = $sheet->getParent();
                $dataSheet = $spreadsheet->createSheet();
                $dataSheet->setTitle('DATA_SISTEMA');
                $dataSheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_VERYHIDDEN);

                $juzgados = Juzgado::pluck('nombre')->toArray();
                $tipos = TipoProceso::pluck('nombre')->toArray();
                $etapas = EtapaProcesal::pluck('nombre')->toArray();

                $this->fillColumn($dataSheet, 'A', $juzgados);
                $this->fillColumn($dataSheet, 'B', $tipos);
                $this->fillColumn($dataSheet, 'C', $etapas);

                $this->applyValidation($sheet, "G2:G{$lastRow}", 'DATA_SISTEMA!$B$1:$B$' . count($tipos));
                $this->applyValidation($sheet, "H2:H{$lastRow}", 'DATA_SISTEMA!$C$1:$C$' . count($etapas));
                $this->applyValidation($sheet, "Q2:Q{$lastRow}", 'DATA_SISTEMA!$A$1:$A$' . count($juzgados));
            },
        ];
    }

    private function fillColumn($sheet, $col, $data) { foreach ($data as $i => $val) { $sheet->setCellValue($col . ($i + 1), $val); } }
    private function applyValidation($sheet, $range, $formula) {
        $validation = $sheet->getDataValidation($range);
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(false);
        $validation->setShowDropDown(true);
        $validation->setFormula1($formula);
    }
}
