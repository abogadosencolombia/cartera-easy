<?php

namespace App\Imports;

use App\Models\ProcessStage;
use App\Models\ProcessType;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ProcessStagesImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            // Hoja 'Etapas' con columnas: etapa, tipo_proceso (opcional), orden (opcional)
            $stageName = trim((string)($row['etapa'] ?? $row['nombre'] ?? ''));
            if ($stageName === '') continue;

            $stage = ProcessStage::firstOrCreate(
                ['slug' => Str::slug($stageName)],
                [
                    'name'  => $stageName,
                    'order' => isset($row['orden']) ? (int) $row['orden'] : null,
                    'active'=> true,
                ]
            );

            // Vincula a un tipo si viene
            $typeName = trim((string)($row['tipo_proceso'] ?? $row['tipo'] ?? ''));
            if ($typeName !== '') {
                $type = ProcessType::firstOrCreate(
                    ['slug' => Str::slug($typeName)],
                    ['name' => Str::upper($typeName), 'active' => true]
                );

                $stage->types()->syncWithoutDetaching([$type->id]);
            }
        }
    }
}
