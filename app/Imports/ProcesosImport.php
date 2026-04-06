<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ProcesosImport implements ToCollection, WithHeadingRow
{
    protected $processedRows = [];

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            // Buscamos el ID de forma estricta
            $idInterno = $row['id_interno'] ?? $row['id_interno_no_modificar'] ?? null;
            if (!is_numeric($idInterno)) $idInterno = null;

            $radicado = preg_replace('/[^0-9]/', '', (string)($row['radicado'] ?? ''));

            if (empty($radicado) && empty($idInterno)) continue;

            $this->processedRows[] = [
                'id_interno'           => $idInterno,
                'radicado'             => $radicado,
                'fecha_radicado'       => $this->transformDate($row['fecha_radicacion'] ?? null),
                'naturaleza'           => $row['naturaleza'] ?? null, // Ya no forzamos CIVIL
                'asunto'               => $row['asunto'] ?? null,
                'tipo_proceso_nombre'  => $row['tipo_de_proceso'] ?? null,
                'etapa_nombre'         => $row['etapa_procesal'] ?? null,
                'fecha_cambio_etapa'   => $this->transformDate($row['fecha_cambio_etapa'] ?? null),
                'estado'               => $row['estado'] ?? null,
                'info_incompleta'      => isset($row['informacion_incompleta']) ? (strtoupper($row['informacion_incompleta']) === 'SI') : null,
                'demandantes_nombres'  => $row['demandantes'] ?? '',
                'demandados_nombres'   => $row['demandados'] ?? '',
                'abogado_gestor'       => $row['abogado_gestor'] ?? null,
                'responsable_revision' => $row['responsable_revision'] ?? null,
                'juzgado_nombre'       => $row['entidad_juzgado_nombre'] ?? $row['entidad_juzgado'] ?? null,
                'correos_juzgado'      => $row['correos_juzgado'] ?? null,
                'fecha_revision'       => $this->transformDate($row['fecha_ultima_revision'] ?? null),
                'fecha_proxima_revision' => $this->transformDate($row['fecha_proxima_revision'] ?? null),
                'ultima_actuacion'     => $row['ultima_actuacion'] ?? null,
                'observaciones'        => $row['observaciones'] ?? null,
                'link_expediente'      => $row['link_expediente_digital'] ?? null,
                'ubicacion_drive'      => $row['ubicacion_drive'] ?? null,
                'correo_radicacion'    => $row['correo_radicacion'] ?? null,
                'nota_cierre'          => $row['nota_cierre'] ?? null,
            ];
        }
    }

    public function getProcessedRows() { return $this->processedRows; }

    private function transformDate($value) {
        if (empty($value)) return null;
        try {
            if (is_numeric($value)) return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('Y-m-d');
            return date('Y-m-d', strtotime($value));
        } catch (\Exception $e) { return null; }
    }
}
