<?php

namespace App\Imports;

use App\Models\Juzgado;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithChunkReading; // Para leer por partes y no bloquearse
use Illuminate\Support\Facades\Log;

class JuzgadosImport implements ToModel, WithStartRow, WithChunkReading
{
    public function startRow(): int
    {
        return 2; // Saltamos los títulos (Fila 1)
    }

    public function chunkSize(): int
    {
        return 500; // Procesamos de a 500 para mayor estabilidad
    }

    public function model(array $row)
    {
        // 1. LIMPIEZA
        // Mapeo por posición fija del PDF:
        // [0]DISTRITO | [1]MUNICIPIO | [2]NOMBRE | [3]CORREO | [4]TELEFONO | [5]DEPTO
        
        $nombre = $this->limpiar($row[2] ?? ''); 
        
        // Si no hay nombre, saltamos la fila
        if (empty($nombre) || strtoupper($nombre) === 'NOMBRE') {
            return null;
        }

        // 2. EXTRACCIÓN DE DATOS
        $distrito     = $this->limpiar($row[0] ?? '');
        $municipio    = $this->limpiar($row[1] ?? '');
        $email_raw    = $this->limpiar($row[3] ?? '');
        $telefono_raw = $this->limpiar($row[4] ?? '');
        $departamento = $this->limpiar($row[5] ?? '');

        // Lógica para detectar si el email se movió de columna (común en PDFs)
        $email = '';
        $telefono = $telefono_raw;

        if (str_contains($email_raw, '@')) {
            $email = $email_raw;
        } elseif (str_contains($telefono_raw, '@')) {
            $email = $telefono_raw;
            $telefono = ''; 
        }

        $datos = [
            'nombre'       => $nombre,
            'distrito'     => $distrito,
            'municipio'    => $municipio,
            'ciudad'       => $municipio, // Llenamos ambos campos por seguridad
            'departamento' => $departamento,
            'email'        => $email,
            'telefono'     => $telefono,
        ];

        // 3. GUARDAR O ACTUALIZAR
        $juzgado = Juzgado::where('nombre', $nombre)->first();

        if ($juzgado) {
            // Si ya existe, actualizamos la información faltante
            $juzgado->update(array_filter($datos)); 
            Log::info("Actualizado: $nombre");
            return null;
        }

        // Si es nuevo, lo creamos
        return new Juzgado($datos);
    }

    private function limpiar($texto)
    {
        if (is_null($texto)) return '';
        return trim(preg_replace('/\s+/', ' ', $texto));
    }
}