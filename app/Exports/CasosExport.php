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
        if (!empty($this->filtros['sin_radicado'])) {
            $query->where(function($q) { $q->whereNull('radicado')->orWhere('radicado', ''); });
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

                $this->applyValidation($sheet, 'N2:N5000', 'DATA_SISTEMA!$A$1:$A$' . count($cooperativas));
                $this->applyValidation($sheet, 'O2:O5000', 'DATA_SISTEMA!$B$1:$B$' . count($juzgados));
                $this->applyValidation($sheet, 'P2:P5000', 'DATA_SISTEMA!$C$1:$C$' . count($especialidades));
                $this->applyValidation($sheet, 'T2:T5000', 'DATA_SISTEMA!$D$1:$D$' . count($etapas));

                $sheet->getStyle('A1:AQ1')->getFont()->setBold(true);
                $sheet->getStyle('A1:AQ1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('4F46E5');
                $sheet->getStyle('A1:AQ1')->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE);
                foreach(range('A','Z') as $col) { $sheet->getColumnDimension($col)->setAutoSize(true); }
                foreach(['AA','AB','AC','AD','AE','AF','AG','AH','AI','AJ','AK','AL','AM','AN','AO','AP','AQ'] as $col) { $sheet->getColumnDimension($col)->setAutoSize(true); }
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
