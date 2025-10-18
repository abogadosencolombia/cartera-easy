<?php

namespace App\Imports;

use App\Models\ProcessType;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ProcessTypesImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            // Acepta columna 'tipo_proceso' o 'nombre'
            $name = trim((string)($row['tipo_proceso'] ?? $row['nombre'] ?? ''));
            if ($name === '') continue;

            ProcessType::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => Str::upper($name), 'active' => true]
            );
        }
    }
}
