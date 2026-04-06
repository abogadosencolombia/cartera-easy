<?php

namespace App\Exports;

use App\Models\Cooperativa;
use App\Models\Juzgado;
use App\Models\EspecialidadJuridica;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

class CasosTemplateExport implements WithHeadings, WithEvents
{
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

                $sheet->getStyle('A1:AN1')->getFont()->setBold(true);
                $sheet->getStyle('A1:AN1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('4F46E5');
                $sheet->getStyle('A1:AN1')->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE);
                foreach(range('A','Z') as $col) { $sheet->getColumnDimension($col)->setAutoSize(true); }
                foreach(['AA','AB','AC','AD','AE','AF','AG','AH','AI','AJ','AK','AL','AM','AN'] as $col) { $sheet->getColumnDimension($col)->setAutoSize(true); }
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
