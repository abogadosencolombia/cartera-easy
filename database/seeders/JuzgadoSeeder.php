<?php

namespace Database\Seeders;

use App\Models\Juzgado;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class JuzgadoSeeder extends Seeder
{
    public function run(): void
    {
        $csvPath = database_path('seeders/data/juzgados.csv');

        if (! file_exists($csvPath)) {
            $this->command->error('El archivo CSV no se encontró en: '.$csvPath);

            return;
        }

        DB::disableQueryLog();
        $file = fopen($csvPath, 'r');

        // Leer la primera línea para adivinar el delimitador
        $firstLine = fgets($file);
        rewind($file); // Volver al inicio del archivo

        // Adivinar si el delimitador es coma o punto y coma
        $delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';
        $this->command->info("Delimitador detectado: '{$delimiter}'");

        // Omitir la cabecera
        fgetcsv($file, 0, $delimiter);

        // La columna de juzgados es la 6ta (índice 5)
        $juzgadoColumnIndex = 5;

        $juzgados = [];
        $juzgadosUnicos = [];

        $this->command->info('Leyendo el archivo CSV de juzgados...');

        // Leer el archivo línea por línea
        while (($row = fgetcsv($file, 0, $delimiter)) !== false) {
            if (isset($row[$juzgadoColumnIndex]) && ! empty(trim($row[$juzgadoColumnIndex]))) {
                $nombreJuzgado = trim($row[$juzgadoColumnIndex]);
                if (! isset($juzgadosUnicos[$nombreJuzgado])) {
                    $juzgados[] = ['nombre' => $nombreJuzgado];
                    $juzgadosUnicos[$nombreJuzgado] = true;
                }
            }
        }
        fclose($file);

        $juzgados = array_map(
            static fn (array $juzgado) => [
                'nombre' => $juzgado['nombre'],
                'tipo' => $juzgado['tipo'] ?? null,
            ],
            array_merge($juzgados, $this->centrosConciliacion())
        );

        if (empty($juzgados)) {
            $this->command->error('No se pudo extraer ningún juzgado. Asegúrate de que el archivo CSV fue guardado desde Excel como "CSV UTF-8 (delimitado por comas)".');

            return;
        }

        $this->command->info('Insertando o actualizando '.count($juzgados).' juzgados/centros únicos en la base de datos...');

        foreach (array_chunk($juzgados, 500) as $chunk) {
            $nombres = array_column($chunk, 'nombre');
            $existentes = Juzgado::withTrashed()
                ->whereIn('nombre', $nombres)
                ->pluck('nombre')
                ->all();

            $existentes = array_flip($existentes);
            $faltantes = array_values(array_filter(
                $chunk,
                static fn (array $juzgado) => ! isset($existentes[$juzgado['nombre']])
            ));

            if (! empty($faltantes)) {
                Juzgado::insert($faltantes);
            }
        }

        foreach ($this->centrosConciliacion() as $centro) {
            $juzgado = Juzgado::withTrashed()->updateOrCreate(
                ['nombre' => $centro['nombre']],
                ['tipo' => $centro['tipo']]
            );

            if ($juzgado->trashed()) {
                $juzgado->restore();
            }
        }

        $this->command->info('¡Seeder de juzgados y centros completado exitosamente!');
    }

    private function centrosConciliacion(): array
    {
        return [
            [
                'nombre' => 'CENTRO DE CONCILIACIÓN, ARBITRAJE Y AMIGABLE COMPOSICIÓN DE LA CÁMARA DE COMERCIO DEL ORIENTE ANTIOQUEÑO',
                'tipo' => 'CENTRO DE CONCILIACIÓN',
            ],
        ];
    }
}
