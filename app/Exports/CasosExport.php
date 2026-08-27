<?php

namespace App\Exports;

use App\Exports\Concerns\PreservesExcelIdentifiers;
use App\Models\Caso;
use App\Models\Cooperativa;
use App\Models\Juzgado;
use App\Models\EspecialidadJuridica;
use App\Models\User;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

class CasosExport implements FromQuery, WithHeadings, WithMapping, WithEvents, WithCustomValueBinder
{
    use PreservesExcelIdentifiers;
    protected $filtros;
    protected ?User $user;

    public function __construct($filtros = [], ?User $user = null)
    {
        $this->filtros = $filtros;
        $this->user = $user;
    }

    public function query()
    {
        $query = Caso::query()->with(['deudor', 'cooperativa', 'juzgado', 'especialidad', 'users', 'codeudores']);

        $this->applyVisibilityScope($query);

        if (!empty($this->filtros['search'])) {
            $words = array_filter(explode(' ', trim($this->filtros['search'])));

            foreach ($words as $word) {
                $cleanWord = preg_replace('/[^0-9]/', '', $word);

                $query->where(function ($q) use ($word, $cleanWord) {
                    $q->where('tipo_proceso', 'ilike', "%{$word}%")
                        ->orWhere('referencia_credito', 'ilike', "%{$word}%")
                        ->orWhere('radicado', 'ilike', "%{$word}%")
                        ->orWhere('subtipo_proceso', 'ilike', "%{$word}%")
                        ->orWhere('subproceso', 'ilike', "%{$word}%")
                        ->orWhere('etapa_procesal', 'ilike', "%{$word}%");

                    if ($cleanWord) {
                        $q->orWhereRaw("regexp_replace(radicado, '[^0-9]', '', 'g') ILIKE ?", ["%{$cleanWord}%"])
                            ->orWhere('referencia_credito', 'ilike', "%{$cleanWord}%");
                    }

                    $q->orWhereHas('deudor', function ($deudorQuery) use ($word, $cleanWord) {
                            $deudorQuery->where('nombre_completo', 'ilike', "%{$word}%");
                            if ($cleanWord) {
                                $deudorQuery->orWhere('numero_documento', 'ilike', "%{$cleanWord}%");
                            }
                        })
                        ->orWhereHas('codeudores', function ($codeudorQuery) use ($word, $cleanWord) {
                            $codeudorQuery->where('nombre_completo', 'ilike', "%{$word}%");
                            if ($cleanWord) {
                                $codeudorQuery->orWhere('numero_documento', 'ilike', "%{$cleanWord}%");
                            }
                        })
                        ->orWhereHas('juzgado', fn ($juzgadoQuery) => $juzgadoQuery->where('nombre', 'ilike', "%{$word}%"))
                        ->orWhereHas('cooperativa', fn ($coopQuery) => $coopQuery->where('nombre', 'ilike', "%{$word}%"));
                });
            }
        }
        
        if (!empty($this->filtros['abogado_id'])) {
            $query->whereHas('users', fn ($uq) => $uq->where('users.id', $this->filtros['abogado_id']));
        }
        if (!empty($this->filtros['cooperativa_id'])) $query->where('cooperativa_id', $this->filtros['cooperativa_id']);
        if (!empty($this->filtros['juzgado_id'])) $query->where('juzgado_id', $this->filtros['juzgado_id']);
        if (!empty($this->filtros['tipo_entidad'])) {
            $tipo = $this->filtros['tipo_entidad'];
            $query->whereHas('juzgado', fn($jq) => $jq->where('nombre', 'ilike', "%{$tipo}%"));
        }
        if (!empty($this->filtros['etapa_procesal'])) $query->where('etapa_procesal', $this->filtros['etapa_procesal']);

        if (!empty($this->filtros['fecha_inicio']) && !empty($this->filtros['fecha_fin'])) {
            $query->whereDate('created_at', '>=', $this->filtros['fecha_inicio'])
                  ->whereDate('created_at', '<=', $this->filtros['fecha_fin']);
        } elseif (!empty($this->filtros['fecha_inicio'])) {
            $query->whereDate('created_at', '>=', $this->filtros['fecha_inicio']);
        } elseif (!empty($this->filtros['fecha_fin'])) {
            $query->whereDate('created_at', '<=', $this->filtros['fecha_fin']);
        }
        
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
            $cutoff = now()->subDays(20);
            $query->where(function ($activityQuery) use ($cutoff) {
                $activityQuery->where('ultima_actividad', '<', $cutoff)
                    ->orWhere(function ($fallbackQuery) use ($cutoff) {
                        $fallbackQuery->whereNull('ultima_actividad')
                            ->where('updated_at', '<', $cutoff);
                    });
            })
                  ->paraSeguimiento();
        }

        $integridadBaja = filter_var($this->filtros['integridad_baja'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($integridadBaja) {
            $query->where('integridad_score', '<', 80)
                  ->paraSeguimiento();
        }

        return $query->orderByDesc('updated_at')->orderByDesc('id');
    }

    private function applyVisibilityScope($query): void
    {
        if (!$this->user) {
            $query->whereRaw('1 = 0');
            return;
        }

        if ($this->user->tipo_usuario === 'admin') {
            return;
        }

        if (in_array($this->user->tipo_usuario, ['gestor', 'abogado'], true)) {
            $cooperativaIds = $this->user->cooperativas()->pluck('cooperativas.id')->all();

            $query->where(function ($q) use ($cooperativaIds) {
                $q->where('user_id', $this->user->id)
                    ->orWhereHas('users', fn ($uq) => $uq->where('users.id', $this->user->id));

                if (!empty($cooperativaIds)) {
                    $q->orWhereIn('cooperativa_id', $cooperativaIds);
                }
            });
            return;
        }

        $query->whereRaw('1 = 0');
    }

    public function headings(): array
    {
        return [
            'ID SISTEMA (NO MODIFICAR)',
            'Radicado (23 digitos)',
            'SPOA/NUNC',
            'Referencia Credito / Pagare',
            'Nombre Deudor',
            'Documento Deudor',
            'Tipo Doc',
            'DV',
            'Celular Deudor',
            'Correo Deudor',
            'Direccion Deudor',
            'Ciudad Deudor',
            'Nombre Codeudor(es)',
            'Documento Codeudor(es)',
            'Celular/Teléfono Codeudor(es)',
            'Correo Codeudor(es)',
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
            'Fecha Inicio Credito (AAAA-MM-DD)',
            'Fecha Demanda/Apertura',
            'Fecha Vencimiento',
            'Fecha Ultimo Pago',
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
        $codeudores = $this->mapCodeudores($caso);

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
            $codeudores['nombres'],
            $codeudores['documentos'],
            $codeudores['celulares'],
            $codeudores['correos'],
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
            $caso->fecha_inicio_credito ? $caso->fecha_inicio_credito->format('Y-m-d') : '',
            $caso->fecha_apertura ? $caso->fecha_apertura->format('Y-m-d') : '',
            $caso->fecha_vencimiento ? $caso->fecha_vencimiento->format('Y-m-d') : '',
            $caso->fecha_ultimo_pago ? $caso->fecha_ultimo_pago->format('Y-m-d') : '',
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


    private function mapCodeudores(Caso $caso): array
    {
        if ($caso->codeudores->isEmpty()) {
            return [
                'nombres' => 'No tiene',
                'documentos' => 'No tiene',
                'celulares' => 'No tiene',
                'correos' => 'No tiene',
            ];
        }

        return [
            'nombres' => $caso->codeudores
                ->map(fn ($codeudor) => $this->valorOPlaceholder($codeudor->nombre_completo))
                ->implode('; '),
            'documentos' => $caso->codeudores
                ->map(fn ($codeudor) => trim(($codeudor->tipo_documento ?: 'Doc') . ': ' . $this->valorOPlaceholder($codeudor->numero_documento)))
                ->implode('; '),
            'celulares' => $caso->codeudores
                ->map(fn ($codeudor) => $this->valorOPlaceholder($codeudor->celular))
                ->implode('; '),
            'correos' => $caso->codeudores
                ->map(fn ($codeudor) => $this->valorOPlaceholder($codeudor->correo))
                ->implode('; '),
        ];
    }

    private function valorOPlaceholder(?string $value): string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : 'No tiene';
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
                    'E' => 35, // Nombre Deudor
                    'M' => 35, // Nombre Codeudor(es)
                    'N' => 24, // Documento Codeudor(es)
                    'O' => 24, // Celular/Teléfono Codeudor(es)
                    'P' => 32, // Correo Codeudor(es)
                    'Q' => 30, // Abogados
                    'R' => 25, // Cooperativa
                    'S' => 35, // Juzgado
                    'T' => 20, // Especialidad
                    'U' => 25, // Tipo Proceso
                    'V' => 25, // Subtipo
                    'W' => 25, // Subproceso
                    'X' => 25, // Etapa
                    'AM' => 50, // Notas Legales
                    'AN' => 40, // Nota Cierre
                    'AK' => 40, // Link Drive
                    'AL' => 40, // Link Expediente
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
                foreach(['AA','AB','AC','AD','AE','AF','AG','AH','AI','AJ','AK','AL','AM','AN','AO','AP','AQ','AR','AS'] as $col) {
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

                $this->applyValidation($sheet, "R2:R{$lastRow}", 'DATA_SISTEMA!$A$1:$A$' . count($cooperativas));
                $this->applyValidation($sheet, "S2:S{$lastRow}", 'DATA_SISTEMA!$B$1:$B$' . count($juzgados));
                $this->applyValidation($sheet, "T2:T{$lastRow}", 'DATA_SISTEMA!$C$1:$C$' . count($especialidades));
                $this->applyValidation($sheet, "X2:X{$lastRow}", 'DATA_SISTEMA!$D$1:$D$' . count($etapas));
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
