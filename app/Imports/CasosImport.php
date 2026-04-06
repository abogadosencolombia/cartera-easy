<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class CasosImport implements ToCollection, WithHeadingRow
{
    protected $processedRows = [];

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            $nombreDeudor = trim($row['nombre_deudor'] ?? '');
            $docDeudor = preg_replace('/[^0-9]/', '', trim((string)($row['documento_deudor'] ?? '')));
            $idSistema = !empty($row['id_sistema_no_modificar']) ? (int)$row['id_sistema_no_modificar'] : null;

            if (empty($nombreDeudor) && empty($docDeudor) && empty($idSistema)) continue;

            $limpiarMonto = fn($val) => (float) preg_replace('/[^0-9.]/', '', str_replace(',', '.', $val ?? '0'));
            
            $this->processedRows[] = [
                'status' => 'success',
                'messages' => [],
                'id_sistema' => $idSistema,
                'radicado' => preg_replace('/[^0-9]/', '', trim((string)($row['radicado_23_digitos'] ?? ''))),
                'referencia_credito' => trim((string)($row['referencia_credito_pagare'] ?? '')),
                'nombre_deudor' => $nombreDeudor,
                'documento_deudor' => $docDeudor,
                'tipo_documento' => strtoupper($row['tipo_documento'] ?? 'CC'),
                'abogados_nombres' => $row['abogados_responsables_nombres_separados_por_coma'] ?? null,
                'cooperativa_nombre' => trim($row['cooperativa_seleccionar_de_lista'] ?? ''),
                'juzgado_nombre' => trim($row['juzgado_seleccionar_de_lista'] ?? ''),
                'especialidad_nombre' => trim($row['especialidad_seleccionar_de_lista'] ?? ''),
                'tipo_proceso' => $row['tipo_proceso'] ?? null,
                'subtipo_proceso' => $row['subtipo_proceso'] ?? null,
                'subproceso' => $row['subproceso'] ?? null,
                'etapa_procesal' => $row['etapa_procesal_seleccionar_de_lista'] ?? null,
                'estado' => strtoupper(trim($row['estado_del_caso_activocerrado'] ?? 'ACTIVO')),
                'estado_proceso' => $row['estado_proceso'] ?? null,
                'tipo_garantia_asociada' => $row['tipo_garantia_seleccionar_de_lista'] ?? null,
                'origen_documental' => $row['origen_documental_seleccionar_de_lista'] ?? null,
                'medio_contacto' => $row['medio_de_contacto_seleccionar_de_lista'] ?? null,
                'monto_total' => $limpiarMonto($row['monto_credito_inicial']),
                'monto_deuda_actual' => $limpiarMonto($row['deuda_actual_capital']),
                'monto_total_pagado' => $limpiarMonto($row['total_pagado_a_la_fecha']),
                'tasa_interes_corriente' => (float) ($row['tasa_interes_corriente'] ?? 0),
                'fecha_inicio_credito' => $this->transformDate($row['fecha_inicio_credito_aaaa_mm_dd']),
                'fecha_apertura' => $this->transformDate($row['fecha_demandapertura_aaaa_mm_dd']),
                'fecha_vencimiento' => $this->transformDate($row['fecha_vencimiento_aaaa_mm_dd']),
                'fecha_ultimo_pago' => $this->transformDate($row['fecha_ultimo_pago_aaaa_mm_dd']),
                'fecha_tasa_interes' => $this->transformDate($row['fecha_tasa_interes_aaaa_mm_dd']),
                'link_drive' => $row['url_carpeta_drive'] ?? null,
                'link_expediente' => $row['url_expediente_digital'] ?? null,
                'notas_legales' => $row['notas_legales_observaciones'] ?? null,
                'nota_cierre' => $row['nota_de_cierre'] ?? null,
                'bloqueado' => strtoupper($row['bloqueado_sino'] ?? 'NO') === 'SI',
                'motivo_bloqueo' => $row['motivo_bloqueo'] ?? null,
            ];
        }
    }

    public function getProcessedRows() { return $this->processedRows; }

    private function transformDate($value): ?string
    {
        if (empty($value)) return null;
        try {
            if (is_numeric($value)) return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('Y-m-d');
            return date('Y-m-d', strtotime($value));
        } catch (\Exception $e) { return null; }
    }
}
