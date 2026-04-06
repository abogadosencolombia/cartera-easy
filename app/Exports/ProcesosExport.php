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
                    ->orWhereHas('demandantes', fn($sq) => $sq->where('nombre_completo', 'ilike', "%{$search}%"));
            });
        }
        
        if (!empty($this->filtros['estado']) && $this->filtros['estado'] !== 'TODOS') $query->where('estado', $this->filtros['estado']);
        if (!empty($this->filtros['sin_radicado'])) $query->where(function($q) { $q->whereNull('radicado')->orWhere('radicado', ''); });

        return $query->latest('updated_at');
    }

    public function headings(): array
    {
        return [
            'ID INTERNO',
            'Radicado',
            'Fecha Radicacion',
            'Naturaleza',
            'Asunto',
            'Tipo de Proceso',
            'Etapa Procesal',
            'Fecha Cambio Etapa',
            'Estado',
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
            $proceso->fecha_radicado ? $proceso->fecha_radicado->format('Y-m-d') : '',
            $proceso->naturaleza,
            $proceso->asunto,
            $proceso->tipoProceso?->nombre,
            $proceso->etapaActual?->nombre,
            $proceso->fecha_cambio_etapa ? $proceso->fecha_cambio_etapa->format('Y-m-d') : '',
            $proceso->estado,
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

                $this->applyValidation($sheet, 'F2:F5000', 'DATA_SISTEMA!$B$1:$B$' . count($tipos));
                $this->applyValidation($sheet, 'G2:G5000', 'DATA_SISTEMA!$C$1:$C$' . count($etapas));
                $this->applyValidation($sheet, 'O2:O5000', 'DATA_SISTEMA!$A$1:$A$' . count($juzgados));

                $sheet->getStyle('A1:Z1')->getFont()->setBold(true);
                $sheet->getStyle('A1:Z1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('4F46E5');
                $sheet->getStyle('A1:Z1')->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE);
                foreach(range('A','Z') as $col) { $sheet->getColumnDimension($col)->setAutoSize(true); }
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
