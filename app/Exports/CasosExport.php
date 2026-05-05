<?php

namespace App\Exports;

use App\Models\Caso;
use App\Models\Cooperativa;
use App\Models\Juzgado;
use App\Models\EspecialidadJuridica;
use App\Models\User;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

class CasosExport implements FromQuery, WithHeadings, WithMapping, WithEvents
{
    protected $filtros;

    public function __construct($filtros = [])
    {
        $this->filtros = $filtros;
    }

    public function query()
    {
        $query = Caso::query()->with(['deudor', 'cooperativa', 'juzgado', 'especialidad', 'users', 'codeudores']);

        if (!empty($this->filtros['search'])) {
            $search = $this->filtros['search'];
            $query->where(function ($q) use ($search) {
                $q->where('radicado', 'ilike', "%{$search}%")
                  ->orWhere('referencia_credito', 'ilike', "%{$search}%")
                  ->orWhereHas('deudor', fn($sq) => $sq->where('nombre_completo', 'ilike', "%{$search}%"));
            });
        }
        
        if (!empty($this->filtros['abogado_id'])) $query->where('user_id', $this->filtros['abogado_id']);
        if (!empty($this->filtros['cooperativa_id'])) $query->where('cooperativa_id', $this->filtros['cooperativa_id']);
        if (!empty($this->filtros['juzgado_id'])) $query->where('juzgado_id', $this->filtros['juzgado_id']);
        if (!empty($this->filtros['tipo_entidad'])) {
            $tipo = $this->filtros['tipo_entidad'];
            $query->whereHas('juzgado', fn($jq) => $jq->where('nombre', 'ilike', "%{$tipo}%"));
        }
        if (!empty($this->filtros['etapa_procesal'])) $query->where('etapa_procesal', $this->filtros['etapa_procesal']);
        
        $sinRadicado = filter_var($this->filtros['sin_radicado'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($sinRadicado) {
            $query->where(function($q) { $q->whereNull('radicado')->orWhere('radicado', ''); });
        }

        $cerrados = filter_var($this->filtros['cerrados'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($cerrados) {
            $query->cerrados();
        }

        $actualizadosHoy = filter_var($this->filtros['actualizados_hoy'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($actualizadosHoy) {
            $query->whereBetween('updated_at', [now()->startOfDay(), now()->endOfDay()]);
        }

        $inactivo20 = filter_var($this->filtros['inactivo_20_dias'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($inactivo20) {
            $query->where('updated_at', '<', now()->subDays(20))
                  ->paraSeguimiento();
        }

        return $query->latest('updated_at');
    }

    public function headings(): array
    {
        return [
            'ID SISTEMA (NO MODIFICAR)',
            'Radicado (23 digitos)',
            'Referencia Credito / Pagare',
            'Nombre Deudor',
            'Documento Deudor',
            'Tipo Doc',
            'DV',
            'Celular Deudor',
            'Correo Deudor',
            'Direccion Deudor',
            'Ciudad Deudor',
            'Codeudores (Nombre (CC: 123); ...)',
            'Abogados Responsables',
            'Cooperativa (Seleccionar lista)',
            'Juzgado (Seleccionar lista)',
            'Especialidad (Seleccionar lista)',
            'Tipo Proceso',
            'Subtipo Proceso',
            'Subproceso',
            'Etapa Procesal (Seleccionar lista)',
            'Estado Caso (ACTIVO/CERRADO)',
            'Estado Proceso',
            'Viabilidad',
            'Tipo Garantia',
            'Origen Documental',
            'Medio de Contacto',
            'Monto Credito Inicial',
            'Deuda Actual (Capital)',
            'Total Pagado',
            'Tasa Interes Corriente (%)',
            'Fecha Inicio Credito (AAAA-MM-DD)',
            'Fecha Demanda/Apertura',
            'Fecha Vencimiento',
            'Fecha Ultimo Pago',
            'Fecha Tasa Interes',
            'URL Carpeta Drive',
            'URL Expediente Digital',
            'Notas Legales / Observaciones',
            'Nota de Cierre',
            'Bloqueado (SI/NO)',
            'Motivo Bloqueo',
            'Registrado Por',
            'Fecha Registro Sistema',
            'Ultima Actualizacion',
        ];
    }

    public function map($caso): array
    {
        $abogados = $caso->users->pluck('name')->implode(', ');
        $codeudores = $caso->codeudores->map(fn($c) => "{$c->nombre_completo} ({$c->tipo_documento}: {$c->numero_documento})")->implode('; ');

        return [
            $caso->id,
            $caso->radicado,
            $caso->es_spoa_nunc ? 'SI' : 'NO',
            $caso->referencia_credito,
            $caso->deudor?->nombre_completo,
            $caso->deudor?->numero_documento,
            $caso->deudor?->tipo_documento,
            $caso->deudor?->dv,
            $caso->deudor?->celular_1,
            $caso->deudor?->correo_1,
            $caso->deudor?->direccion,
            $caso->deudor?->ciudad,
            $codeudores,
            $abogados,
            $caso->cooperativa?->nombre,
            $caso->juzgado?->nombre,
            $caso->especialidad?->nombre,
            $caso->tipo_proceso,
            $caso->subtipo_proceso,
            $caso->subproceso,
            $caso->etapa_procesal,
            $caso->estado ?? 'ACTIVO',
            $caso->estado_proceso,
            strtoupper($caso->viabilidad_estado ?? 'PENDIENTE'),
            $caso->tipo_garantia_asociada,
            $caso->origen_documental,
            $caso->medio_contacto,
            $caso->monto_total,
            $caso->monto_deuda_actual,
            $caso->monto_total_pagado,
            $caso->tasa_interes_corriente,
            $caso->fecha_inicio_credito ? $caso->fecha_inicio_credito->format('Y-m-d') : '',
            $caso->fecha_apertura ? $caso->fecha_apertura->format('Y-m-d') : '',
            $caso->fecha_vencimiento ? $caso->fecha_vencimiento->format('Y-m-d') : '',
            $caso->fecha_ultimo_pago ? $caso->fecha_ultimo_pago->format('Y-m-d') : '',
            $caso->fecha_tasa_interes ? $caso->fecha_tasa_interes->format('Y-m-d') : '',
            $caso->link_drive,
            $caso->link_expediente,
            $caso->notas_legales,
            $caso->nota_cierre,
            $caso->bloqueado ? 'SI' : 'NO',
            $caso->motivo_bloqueo,
            $caso->user?->name ?? 'Sistema',
            $caso->created_at->format('Y-m-d H:i'),
            $caso->updated_at->format('Y-m-d H:i'),
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

                // 3. Bordes Finos
                $sheet->getStyle($fullRange)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $sheet->getStyle($fullRange)->getBorders()->getAllBorders()->getColor()->setARGB('E2E8F0');

                // 4. Ajuste de Anchos y Text Wrapping
                $longTextFields = [
                    'D' => 35, // Nombre Deudor
                    'L' => 45, // Codeudores
                    'M' => 30, // Abogados
                    'N' => 25, // Cooperativa
                    'O' => 35, // Juzgado
                    'P' => 20, // Especialidad
                    'Q' => 25, // Tipo Proceso
                    'R' => 25, // Subtipo
                    'S' => 25, // Subproceso
                    'T' => 25, // Etapa
                    'AL' => 50, // Notas Legales
                    'AM' => 40, // Nota Cierre
                    'AJ' => 40, // Link Drive
                    'AK' => 40, // Link Expediente
                ];

                foreach ($longTextFields as $col => $width) {
                    $sheet->getColumnDimension($col)->setAutoSize(false);
                    $sheet->getColumnDimension($col)->setWidth($width);
                    $sheet->getStyle("{$col}2:{$col}{$lastRow}")->getAlignment()->setWrapText(true);
                }

                // Auto-size para el resto
                foreach(range('A','Z') as $col) {
                    if (!isset($longTextFields[$col])) $sheet->getColumnDimension($col)->setAutoSize(true);
                }
                foreach(['AA','AB','AC','AD','AE','AF','AG','AH','AI','AN','AO','AP','AQ'] as $col) {
                    if (!isset($longTextFields[$col])) $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                // 5. Cebra
                for ($row = 2; $row <= $lastRow; $row++) {
                    if ($row % 2 == 0) {
                        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()
                            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                            ->getStartColor()->setARGB('F8FAFC');
                    }
                }

                // 6. Validaciones
                $spreadsheet = $sheet->getParent();
                $dataSheet = $spreadsheet->createSheet();
                $dataSheet->setTitle('DATA_SISTEMA');
                $dataSheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_VERYHIDDEN);

                $cooperativas = Cooperativa::pluck('nombre')->toArray();
                $juzgados = Juzgado::pluck('nombre')->toArray();
                $especialidades = EspecialidadJuridica::pluck('nombre')->toArray();
                $etapas = \DB::table('etapas_procesales')->pluck('nombre')->toArray();

                $this->fillColumn($dataSheet, 'A', $cooperativas);
                $this->fillColumn($dataSheet, 'B', $juzgados);
                $this->fillColumn($dataSheet, 'C', $especialidades);
                $this->fillColumn($dataSheet, 'D', $etapas);

                $this->applyValidation($sheet, "N2:N{$lastRow}", 'DATA_SISTEMA!$A$1:$A$' . count($cooperativas));
                $this->applyValidation($sheet, "O2:O{$lastRow}", 'DATA_SISTEMA!$B$1:$B$' . count($juzgados));
                $this->applyValidation($sheet, "P2:P{$lastRow}", 'DATA_SISTEMA!$C$1:$C$' . count($especialidades));
                $this->applyValidation($sheet, "T2:T{$lastRow}", 'DATA_SISTEMA!$D$1:$D$' . count($etapas));
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
