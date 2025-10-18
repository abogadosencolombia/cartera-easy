<?php

namespace App\Imports;

use App\Models\ProcesoRadicado;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Illuminate\Support\Collection;

class ProcesoRadicadosImport implements ToCollection, WithHeadingRow
{
    public function headingRow(): int { return 1; }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            // Normaliza claves del encabezado
            $norm = [];
            foreach ($row->toArray() as $k => $v) {
                $key = $this->normalizeKey($k);
                $norm[$key] = is_string($v) ? trim($v) : $v;
            }

            // Mapeo flexible de encabezados → atributos del modelo
            $map = [
                'abogado'                                 => 'abogado',
                'radicado_no'                             => 'radicado',
                'radicado'                                => 'radicado',
                'fecha_de_radicado'                       => 'fecha_radicado',
                'juzgado_entidad'                         => 'juzgado_entidad',
                'juzgado___entidad'                       => 'juzgado_entidad',
                'naturaleza'                              => 'naturaleza',
                'tipo_de_proceso'                         => 'tipo_proceso',
                'tipo_proceso'                            => 'tipo_proceso',
                'asunto'                                  => 'asunto',
                'demandante___denunciante'                => 'demandante',
                'demandado___denunciado'                  => 'demandado',
                'correo_de_radicacion'                    => 'correo_radicacion',
                'fecha_de_revision'                       => 'fecha_revision',
                'responsable_revision'                    => 'responsable_revision',
                'fecha_de_proxima_revision_revision'      => 'fecha_proxima_revision',
                'fecha_de_proxima_revision'               => 'fecha_proxima_revision',
                'observaciones'                           => 'observaciones',
                'ultima_actuacion'                        => 'ultima_actuacion',
                'link_de_expediente_digital'              => 'link_expediente',
                'correos_del_juzgado___entidad'           => 'correos_juzgado',
                'ubicacion_drive'                         => 'ubicacion_drive',
            ];

            $data = [];
            foreach ($map as $from => $to) {
                if (Arr::exists($norm, $from)) {
                    $data[$to] = $norm[$from];
                }
            }

            if (empty($data['radicado'])) {
                // Sin radicado no se puede identificar/crear
                continue;
            }

            // Parseo de fechas robusto (Excel serial o string)
            foreach (['fecha_radicado','fecha_revision','fecha_proxima_revision'] as $field) {
                if (!empty($data[$field])) {
                    $data[$field] = $this->parseDate($data[$field]);
                } else {
                    $data[$field] = null;
                }
            }

            // Creador
            $data['created_by'] = auth()->id();

            // UPSERT por radicado (sin romper si ya existe)
            ProcesoRadicado::updateOrCreate(
                ['radicado' => $data['radicado']],
                $data
            );
        }
    }

    private function normalizeKey(string $key): string
    {
        // pasa a ascii, quita tildes, reemplaza separadores y limpia símbolos
        $k = Str::of($key)
            ->lower()
            ->replaceMatches('/\s+/', ' ')
            ->replace('/', ' / ')
            ->ascii()
            ->replace(' / ', '___') // preservar "juzgado / entidad"
            ->replace(' ', '_')
            ->replace(['.',',','"',"'",'%','-','__'], '_')
            ->trim('_')
            ->toString();

        return $k;
    }

    private function parseDate($value): ?string
    {
        if ($value === null || $value === '') return null;

        // Excel serial (numérico)
        if (is_numeric($value)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString();
            } catch (\Throwable $e) {
                // fall-through
            }
        }

        // Variantes comunes
        $cands = [
            'Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y', 'm-d-Y',
            'Y/m/d', 'Y.m.d', 'd.m.Y',
        ];
        foreach ($cands as $fmt) {
            try {
                return Carbon::createFromFormat($fmt, (string) $value)->toDateString();
            } catch (\Throwable $e) {}
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
