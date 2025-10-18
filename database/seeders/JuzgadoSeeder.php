<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Juzgado;
use Illuminate\Support\Facades\DB;

class JuzgadoSeeder extends Seeder
{
    public function run(): void
    {
        $csvPath = database_path('seeders/data/juzgados.csv');

        if (!file_exists($csvPath)) {
            $this->command->error("El archivo CSV no se encontró en: " . $csvPath);
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
        $header = fgetcsv($file, 0, $delimiter);

        // La columna de juzgados es la 6ta (índice 5)
        $juzgadoColumnIndex = 5;

        $juzgados = [];
        $juzgadosUnicos = [];

        $this->command->info('Leyendo el archivo CSV de juzgados...');

        // Leer el archivo línea por línea
        while (($row = fgetcsv($file, 0, $delimiter)) !== false) {
            if (isset($row[$juzgadoColumnIndex]) && !empty(trim($row[$juzgadoColumnIndex]))) {
                $nombreJuzgado = trim($row[$juzgadoColumnIndex]);
                if (!isset($juzgadosUnicos[$nombreJuzgado])) {
                    $juzgados[] = ['nombre' => $nombreJuzgado];
                    $juzgadosUnicos[$nombreJuzgado] = true;
                }
            }
        }
        fclose($file);

        if (empty($juzgados)) {
            $this->command->error('No se pudo extraer ningún juzgado. Asegúrate de que el archivo CSV fue guardado desde Excel como "CSV UTF-8 (delimitado por comas)".');
            return;
        }

        $this->command->info('Insertando ' . count($juzgados) . ' juzgados únicos en la base de datos...');

        // Insertar los datos en lotes de 500 para no sobrecargar la memoria
        foreach (array_chunk($juzgados, 500) as $chunk) {
            Juzgado::insertOrIgnore($chunk);
        }

        $this->command->info('¡Seeder de juzgados completado exitosamente!');
    }
}
