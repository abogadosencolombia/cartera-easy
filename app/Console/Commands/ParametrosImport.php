<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Models\TipoProceso;
use App\Models\EtapaProceso;

class ParametrosImport extends Command
{
    protected $signature = 'parametros:import
        {file : Ruta del Excel. Si es relativa, se asume storage/app}
        {--tipos : Importar solo tipos}
        {--etapas : Importar solo etapas}
        {--sheet-tipos= : Hoja para tipos (por defecto la primera)}
        {--sheet-etapas= : Hoja para etapas (por defecto la primera)}
        {--col-tipo= : Nombre exacto de la columna de tipo (auto: tipo|tipo_proceso|proceso)}
        {--col-etapa= : Nombre exacto de la columna de etapa (auto: etapa|etapa_procesal|etapa_proceso|fase)}
        {--show-sheets : Listar hojas y salir}
        {--dry-run : No escribir en BD, solo contar}';

    protected $description = 'Importa tipos de proceso y etapas desde Excel. Admite encabezados tipo_proceso y etapa_procesal.';

    public function handle(): int
    {
        $path = $this->argument('file');

        // Si pasas un nombre “corto”, lo buscamos en storage/app
        if (!preg_match('~^([a-zA-Z]:\\\\|/|\\\\)~', $path)) {
            $path = storage_path('app/' . ltrim($path, '/\\'));
        }

        if (!is_file($path)) {
            $this->error('No encuentro el archivo: ' . $path);
            return self::FAILURE;
        }

        $spread = IOFactory::load($path);

        if ($this->option('show-sheets')) {
            $this->line('Usando archivo: ' . $this->humanPath($path));
            $this->line('Hojas disponibles:');
            foreach ($spread->getSheetNames() as $i => $name) {
                $this->line(" - [$i] $name");
            }
            return self::SUCCESS;
        }

        $this->info('Usando archivo: ' . $this->humanPath($path));

        $doTipos  = $this->option('tipos')  || !$this->option('etapas');
        $doEtapas = $this->option('etapas') || !$this->option('tipos');

        $sheetTipos  = $this->option('sheet-tipos')  ?: $spread->getSheetNames()[0];
        $sheetEtapas = $this->option('sheet-etapas') ?: $spread->getSheetNames()[0];

        if ($doTipos) {
            [$ins, $upd, $sk] = $this->importTipos($spread->getSheetByName($sheetTipos));
            $this->line("TIPOS -> insertados: $ins, actualizados: $upd, saltados: $sk");
        }

        if ($doEtapas) {
            [$ins, $upd, $sk, $sinTipo] = $this->importEtapas($spread->getSheetByName($sheetEtapas));
            $this->line("ETAPAS -> insertados: $ins, actualizados: $upd, saltados: $sk, sin_tipo: $sinTipo");
        }

        return self::SUCCESS;
    }

    private function humanPath(string $abs): string
    {
        $rel = str_replace(storage_path('app') . DIRECTORY_SEPARATOR, 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR, $abs);
        return $rel;
    }

    private function normalizedHeaders($sheet): array
    {
        $row1 = $sheet->rangeToArray('A1:' . $sheet->getHighestColumn() . '1', null, true, true, true)[1] ?? [];
        return collect($row1)->map(function ($v) {
            $v = Str::of((string) $v)->trim()->lower()->ascii()->snake();
            return (string) $v;
        })->values()->all();
    }

    private function importTipos($sheet): array
    {
        $headers = $this->normalizedHeaders($sheet);
        $this->line('Headers TIPOS: ' . implode(', ', $headers));

        $colTipo = $this->option('col-tipo');
        if (!$colTipo) {
            $colTipo = collect(['tipo', 'tipo_proceso', 'proceso'])
                ->first(fn ($k) => in_array($k, $headers));
        }
        if (!$colTipo) {
            $this->warn('No encuentro columna de tipo. (busco: tipo|tipo_proceso|proceso)');
            return [0, 0, 0];
        }

        $rows = $sheet->toArray(null, true, true, true);
        array_shift($rows); // fuera encabezado

        $iTipo = array_search($colTipo, $headers);

        $nombres = collect($rows)
            ->map(function ($row) use ($iTipo) {
                $vals = array_values($row);
                $name = Str::of($vals[$iTipo] ?? '')
                    ->trim()->ascii()->upper();
                return (string) $name;
            })
            ->filter()
            ->unique()
            ->values();

        $ins = $upd = $sk = 0;
        foreach ($nombres as $nombre) {
            $existe = TipoProceso::where('nombre', $nombre)->first();
            if (!$existe) {
                if (!$this->option('dry-run')) {
                    TipoProceso::create(['nombre' => $nombre]);
                }
                $ins++;
            } else {
                $upd++; // no cambiamos nada, solo para contabilidad
            }
        }

        return [$ins, $upd, $sk];
    }

    private function importEtapas($sheet): array
    {
        $headers = $this->normalizedHeaders($sheet);
        $this->line('Headers ETAPAS: ' . implode(', ', $headers));

        $colTipo  = $this->option('col-tipo')  ?: collect(['tipo', 'tipo_proceso', 'proceso'])->first(fn ($k) => in_array($k, $headers));
        $colEtapa = $this->option('col-etapa') ?: collect(['etapa', 'etapa_procesal', 'etapa_proceso', 'fase'])->first(fn ($k) => in_array($k, $headers));

        if (!$colTipo || !$colEtapa) {
            $this->warn('No encuentro columnas para ETAPAS. (busco tipo|tipo_proceso|proceso y etapa|etapa_procesal|etapa_proceso|fase)');
            return [0, 0, 0, 0];
        }

        $rows = $sheet->toArray(null, true, true, true);
        array_shift($rows);

        $iTipo  = array_search($colTipo, $headers);
        $iEtapa = array_search($colEtapa, $headers);

        $data = collect($rows)
            ->map(function ($row) use ($iTipo, $iEtapa) {
                $vals  = array_values($row);
                $tipo  = Str::of($vals[$iTipo]  ?? '')->trim()->ascii()->upper();
                $etapa = Str::of($vals[$iEtapa] ?? '')->trim()->ascii()->upper();
                return ['tipo' => (string) $tipo, 'etapa' => (string) $etapa];
            })
            ->filter(fn ($r) => $r['tipo'] !== '' && $r['etapa'] !== '')
            ->unique(fn ($r) => $r['tipo'] . '|' . $r['etapa'])
            ->values();

        $mapTipos = TipoProceso::pluck('id', 'nombre');

        $ins = $upd = $sk = $sinTipo = 0;
        foreach ($data as $r) {
            $tipoId = $mapTipos[$r['tipo']] ?? null;

            // crea el tipo si no existe
            if (!$tipoId) {
                if ($this->option('dry-run')) { $sinTipo++; continue; }
                $tipo   = TipoProceso::firstOrCreate(['nombre' => $r['tipo']]);
                $tipoId = $tipo->id;
                $mapTipos[$r['tipo']] = $tipoId;
            }

            $existe = EtapaProceso::where('tipo_proceso_id', $tipoId)
                ->where('nombre', $r['etapa'])
                ->first();

            if (!$existe) {
                if (!$this->option('dry-run')) {
                    EtapaProceso::create([
                        'tipo_proceso_id' => $tipoId,
                        'nombre'          => $r['etapa'],
                        'orden'           => 0,
                    ]);
                }
                $ins++;
            } else {
                $upd++;
            }
        }

        return [$ins, $upd, $sk, $sinTipo];
    }
}
