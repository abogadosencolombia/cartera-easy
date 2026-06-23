<?php

namespace App\Console\Commands;

use App\Models\Caso;
use App\Models\Cooperativa;
use App\Models\Persona;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportarNoralbaCrearcoop extends Command
{
    protected $signature = 'importar:noralba-crearcoop {file=NORALBA -SEGUIMIENTO ACTUALIZADO JUNIO.xlsx} {--dry-run : Analiza el archivo sin escribir en base de datos}';

    protected $description = 'Importa personas y casos de NORALBA para CREARCOOP desde las hojas ACTIVO y CASTIGADO.';

    public function handle(): int
    {
        $file = base_path($this->argument('file'));

        if (!is_file($file)) {
            $this->error("No se encontró el archivo: {$file}");
            return self::FAILURE;
        }

        $cooperativa = Cooperativa::where('nombre', 'ilike', 'CREARCOOP')->first();
        if (!$cooperativa) {
            $this->error('No existe la cooperativa CREARCOOP.');
            return self::FAILURE;
        }

        $responsable = $this->responsablePorDefecto($cooperativa->id);
        if (!$responsable) {
            $this->error('No se encontró un usuario responsable para CREARCOOP.');
            return self::FAILURE;
        }

        $rows = $this->leerFilas($file);
        if (empty($rows)) {
            $this->warn('No se encontraron filas importables.');
            return self::SUCCESS;
        }

        $stats = [
            'filas' => count($rows),
            'personas_creadas' => 0,
            'personas_actualizadas' => 0,
            'casos_creados' => 0,
            'casos_actualizados' => 0,
            'omitidas' => 0,
        ];

        $runner = function () use ($rows, $cooperativa, $responsable, &$stats) {
            foreach ($rows as $row) {
                if (empty($row['documento']) || empty($row['nombre'])) {
                    $stats['omitidas']++;
                    continue;
                }

                $persona = $this->upsertPersona($row, $cooperativa->id, $stats);
                $this->upsertCaso($row, $persona, $cooperativa->id, $responsable->id, $stats);
            }
        };

        if ($this->option('dry-run')) {
            DB::beginTransaction();
            try {
                $runner();
            } finally {
                DB::rollBack();
            }
        } else {
            DB::transaction($runner);
        }

        $this->table(['Métrica', 'Total'], collect($stats)->map(fn ($value, $key) => [$key, $value])->all());
        $this->info($this->option('dry-run') ? 'Dry-run completado. No se escribieron datos.' : 'Importación completada.');

        return self::SUCCESS;
    }

    private function leerFilas(string $file): array
    {
        $reader = IOFactory::createReaderForFile($file);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($file);
        $rows = [];

        foreach (['ACTIVO', 'CASTIGADO'] as $sheetName) {
            $sheet = $spreadsheet->getSheetByName($sheetName);
            if (!$sheet) {
                $this->warn("No existe la hoja {$sheetName}.");
                continue;
            }

            $fallbackEstadoCartera = $sheetName === 'ACTIVO' ? 'ACTIVO' : 'CASTIGADO';
            $highestRow = $sheet->getHighestRow();

            for ($row = 3; $row <= $highestRow; $row++) {
                $documento = $this->normalizarDocumento($sheet->getCell("A{$row}")->getValue());
                $nombre = trim((string) $sheet->getCell("B{$row}")->getValue());
                $pagare = $this->normalizarDocumento($sheet->getCell("C{$row}")->getValue());
                $capital = $this->normalizarMonto($sheet->getCell("D{$row}")->getValue());
                $estadoProceso = $this->normalizarTexto($sheet->getCell("E{$row}")->getValue());
                $estadoCartera = $this->normalizarEstadoCartera($sheet->getCell("F{$row}")->getValue(), $fallbackEstadoCartera);
                $drive = trim((string) $sheet->getCell("G{$row}")->getValue());

                if ($documento === '' && $nombre === '' && $pagare === '') {
                    continue;
                }

                $rows[] = compact('documento', 'nombre', 'pagare', 'capital', 'estadoProceso', 'estadoCartera', 'drive') + [
                    'sheet' => $sheetName,
                    'row' => $row,
                ];
            }
        }

        return $rows;
    }

    private function upsertPersona(array $row, int $cooperativaId, array &$stats): Persona
    {
        $persona = Persona::withTrashed()
            ->whereRaw("regexp_replace(numero_documento, '[^0-9]', '', 'g') = ?", [$row['documento']])
            ->first();

        $socialLinks = $this->socialLinksConDrive($persona?->social_links ?? [], $row['drive']);
        $data = [
            'nombre_completo' => $row['nombre'],
            'tipo_documento' => 'CC',
            'numero_documento' => $row['documento'],
            'estado_cartera' => $row['estadoCartera'],
            'social_links' => $socialLinks,
            'sin_empresa_o_cooperativa' => false,
        ];

        if ($persona) {
            $persona->forceFill($data)->save();
            if ($persona->trashed()) {
                $persona->restore();
            }
            $stats['personas_actualizadas']++;
        } else {
            $persona = Persona::create($data);
            $stats['personas_creadas']++;
        }

        $persona->cooperativas()->syncWithoutDetaching([$cooperativaId]);

        return $persona;
    }

    private function upsertCaso(array $row, Persona $persona, int $cooperativaId, int $responsableId, array &$stats): Caso
    {
        $caso = null;

        if ($row['pagare'] !== '') {
            $caso = Caso::whereRaw("regexp_replace(COALESCE(referencia_credito, ''), '[^0-9]', '', 'g') = ?", [$row['pagare']])->first();
        }

        if (!$caso && $row['pagare'] !== '') {
            $caso = Caso::where('deudor_id', $persona->id)
                ->whereRaw("regexp_replace(COALESCE(referencia_credito, ''), '[^0-9]', '', 'g') = ?", [$row['pagare']])
                ->first();
        }

        $notaImportacion = "Importado desde NORALBA - seguimiento actualizado junio. Hoja {$row['sheet']}, fila {$row['row']}.";
        $notasLegales = trim((string) ($caso?->notas_legales ?? ''));
        if (!str_contains($notasLegales, $notaImportacion)) {
            $notasLegales = trim($notasLegales === '' ? $notaImportacion : $notasLegales . "
" . $notaImportacion);
        }

        $data = [
            'cooperativa_id' => $cooperativaId,
            'user_id' => $caso?->user_id ?: $responsableId,
            'deudor_id' => $persona->id,
            'referencia_credito' => $row['pagare'] ?: null,
            'tipo_proceso' => 'EJECUTIVO',
            'estado_proceso' => $this->estadoProcesoPermitido($row['estadoProceso']),
            'estado' => $row['estadoCartera'],
            'etapa_procesal' => $row['estadoProceso'],
            'tipo_garantia_asociada' => 'sin garantía',
            'fecha_apertura' => $caso?->fecha_apertura ?: now()->toDateString(),
            'fecha_inicio_credito' => $caso?->fecha_inicio_credito ?: now()->toDateString(),
            'monto_total' => $row['capital'] ?? 0,
            'monto_deuda_actual' => $row['capital'] ?? 0,
            'monto_total_pagado' => $caso?->monto_total_pagado ?? 0,
            'tasa_interes_corriente' => $caso?->tasa_interes_corriente ?? 0,
            'origen_documental' => 'pagaré',
            'medio_contacto' => $caso?->medio_contacto ?: 'otro',
            'link_drive' => $row['drive'] ?: $caso?->link_drive,
            'notas_legales' => trim((string) ($caso?->notas_legales ? $caso->notas_legales . "
" : '') . "Importado desde NORALBA - seguimiento actualizado junio. Hoja {$row['sheet']}, fila {$row['row']}.") ,
            'bloqueado' => $caso?->bloqueado ?? false,
            'sin_codeudores' => true,
        ];

        if ($caso) {
            $caso->forceFill($data)->save();
            $stats['casos_actualizados']++;
        } else {
            $caso = Caso::create($data);
            $stats['casos_creados']++;
        }

        $caso->users()->syncWithoutDetaching([$responsableId]);

        return $caso;
    }

    private function estadoProcesoPermitido(?string $estadoProceso): string
    {
        $estado = mb_strtoupper(trim((string) $estadoProceso));

        if ($estado === '' || str_contains($estado, 'SIN DEMANDA') || str_contains($estado, 'PENDIENTE')) {
            return 'prejurídico';
        }

        return 'demandado';
    }

    private function responsablePorDefecto(int $cooperativaId): ?User
    {
        return User::where('name', 'ilike', 'NUBIA AIDE GALLEGO')->first()
            ?? User::whereHas('cooperativas', fn ($query) => $query->where('cooperativas.id', $cooperativaId))
                ->whereIn('tipo_usuario', ['gestor', 'abogado', 'admin'])
                ->orderByRaw("CASE WHEN tipo_usuario = 'gestor' THEN 0 WHEN tipo_usuario = 'abogado' THEN 1 ELSE 2 END")
                ->orderBy('id')
                ->first();
    }

    private function socialLinksConDrive(array $links, string $drive): array
    {
        $links = array_values(array_filter($links, fn ($link) => is_array($link)));

        if ($drive === '') {
            return $links;
        }

        $exists = collect($links)->contains(fn ($link) => trim((string) ($link['url'] ?? '')) === $drive);
        if (!$exists) {
            $links[] = ['label' => 'Drive CREARCOOP', 'url' => $drive];
        }

        return $links;
    }

    private function normalizarDocumento(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_int($value) || is_float($value)) {
            return number_format((float) $value, 0, '', '');
        }

        return preg_replace('/[^0-9]/', '', trim((string) $value)) ?? '';
    }

    private function normalizarMonto(mixed $value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $clean = preg_replace('/[^0-9.,-]/', '', (string) $value);
        $clean = str_replace(',', '.', $clean);

        return is_numeric($clean) ? (float) $clean : null;
    }

    private function normalizarTexto(mixed $value): ?string
    {
        $text = trim((string) $value);
        return $text === '' ? null : mb_strtoupper($text);
    }

    private function normalizarEstadoCartera(mixed $value, string $fallback): string
    {
        $text = $this->normalizarTexto($value) ?: $fallback;
        return in_array($text, ['ACTIVO', 'CASTIGADO'], true) ? $text : 'NO APLICA';
    }
}
