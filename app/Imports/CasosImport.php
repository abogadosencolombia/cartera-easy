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
            $get = function (array|string $keys, mixed $default = null) use ($row): mixed {
                foreach ((array) $keys as $key) {
                    if (isset($row[$key])) {
                        return $row[$key];
                    }
                }

                return $default;
            };

            $siNo = function (array|string $keys) use ($get): ?bool {
                $value = $get($keys);
                if ($value === null || trim((string) $value) === '') {
                    return null;
                }

                return strtoupper(trim((string) $value)) === 'SI';
            };

            $nombreDeudor = trim((string) $get('nombre_deudor', ''));
            $docDeudor = preg_replace('/[^0-9]/', '', trim((string) $get('documento_deudor', '')));
            $idSistemaRaw = $get(['id_sistema_no_modificar', 'id_sistema']);
            $idSistema = !empty($idSistemaRaw) ? (int) $idSistemaRaw : null;

            if (empty($nombreDeudor) && empty($docDeudor) && empty($idSistema)) {
                continue;
            }

            $limpiarMonto = function ($val): ?float {
                if ($val === null || trim((string) $val) === '') {
                    return null;
                }

                return (float) preg_replace('/[^0-9.]/', '', str_replace(',', '.', $val));
            };

            $this->processedRows[] = [
                'status' => 'success',
                'messages' => [],
                'id_sistema' => $idSistema,
                'radicado' => preg_replace('/[^0-9]/', '', trim((string) $get('radicado_23_digitos', ''))),
                'es_spoa_nunc' => $siNo(['es_spoa_nunc_sino', 'spoanunc', 'spoa_nunc']),
                'referencia_credito' => trim((string) $get('referencia_credito_pagare', '')),
                'nombre_deudor' => $nombreDeudor,
                'documento_deudor' => $docDeudor,
                'tipo_documento' => strtoupper((string) $get(['tipo_documento', 'tipo_doc'], 'CC')),
                'dv' => $get('dv'),
                'celular_deudor' => $get('celular_deudor'),
                'correo_deudor' => $get('correo_deudor'),
                'abogados_nombres' => $get(['abogados_responsables_nombres_separados_por_coma', 'abogados_responsables']),
                'cooperativa_nombre' => trim((string) $get(['cooperativa_seleccionar_de_lista', 'cooperativa_seleccionar_lista'], '')),
                'juzgado_nombre' => trim((string) $get(['juzgado_seleccionar_de_lista', 'juzgado_seleccionar_lista'], '')),
                'especialidad_nombre' => trim((string) $get(['especialidad_seleccionar_de_lista', 'especialidad_seleccionar_lista'], '')),
                'tipo_proceso' => $get('tipo_proceso'),
                'subtipo_proceso' => $get('subtipo_proceso'),
                'subproceso' => $get('subproceso'),
                'etapa_procesal' => $get(['etapa_procesal_seleccionar_de_lista', 'etapa_procesal_seleccionar_lista', 'etapa_procesal']),
                'estado' => $this->normalizarTextoMayuscula($get(['estado_del_caso_activocerrado', 'estado_caso_activocerrado'])),
                'estado_proceso' => $get('estado_proceso'),
                'tipo_garantia_asociada' => $get(['tipo_garantia_seleccionar_de_lista', 'tipo_garantia_seleccionar_lista', 'tipo_garantia']),
                'origen_documental' => $get(['origen_documental_seleccionar_de_lista', 'origen_documental_seleccionar_lista', 'origen_documental']),
                'medio_contacto' => $get(['medio_de_contacto_seleccionar_de_lista', 'medio_de_contacto_seleccionar_lista', 'medio_de_contacto']),
                'monto_total' => $limpiarMonto($get('monto_credito_inicial')),
                'monto_deuda_actual' => $limpiarMonto($get('deuda_actual_capital')),
                'monto_total_pagado' => $limpiarMonto($get(['total_pagado_a_la_fecha', 'total_pagado'])),
                'fecha_inicio_credito' => $this->transformDate($get(['fecha_inicio_credito_aaaa_mm_dd', 'fecha_inicio_credito'])),
                'fecha_apertura' => $this->transformDate($get(['fecha_demandapertura_aaaa_mm_dd', 'fecha_demandaapertura', 'fecha_demandapertura'])),
                'fecha_vencimiento' => $this->transformDate($get(['fecha_vencimiento_aaaa_mm_dd', 'fecha_vencimiento'])),
                'fecha_ultimo_pago' => $this->transformDate($get(['fecha_ultimo_pago_aaaa_mm_dd', 'fecha_ultimo_pago'])),
                'link_drive' => $get('url_carpeta_drive'),
                'link_expediente' => $get('url_expediente_digital'),
                'notas_legales' => $get('notas_legales_observaciones'),
                'nota_cierre' => $get('nota_de_cierre'),
                'bloqueado' => $siNo('bloqueado_sino'),
                'motivo_bloqueo' => $get('motivo_bloqueo'),
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

    private function normalizarTextoMayuscula($value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return strtoupper(trim((string) $value));
    }
}
