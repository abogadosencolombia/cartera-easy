<?php

namespace App\Imports;

use App\Models\Juzgado;
use App\Models\Persona;
use App\Models\ProcesoRadicado;
use App\Models\TipoProceso;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class ProcesosImport implements ToModel, WithHeadingRow, WithValidation, WithChunkReading
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        // --- BÚSQUEDA O CREACIÓN DE MODELOS RELACIONADOS ---
        
        // La librería convierte los encabezados a snake_case, ej: "Juzgado / Entidad" -> "juzgado_entidad"
        $abogado = User::firstWhere('name', $row['abogado_gestor']);
        $responsable = User::firstWhere('name', $row['responsable_revision']);
        $juzgado = Juzgado::firstOrCreate(['nombre' => $row['juzgado_entidad']]);
        $tipoProceso = TipoProceso::firstOrCreate(['nombre' => $row['tipo_de_proceso']]);
        $demandante = Persona::firstOrCreate(['nombre_completo' => $row['demandante_denunciante']]);
        $demandado = Persona::firstOrCreate(['nombre_completo' => $row['demandado_denunciado']]);

        return new ProcesoRadicado([
            // Mapeo directo de columnas
            'radicado'          => $row['radicado'],
            'asunto'            => $row['asunto'],
            'naturaleza'        => $row['naturaleza'],
            'ultima_actuacion'  => $row['ultima_actuacion'],
            'correo_radicacion' => $row['correo_radicacion'],
            'correos_juzgado'   => $row['correos_del_juzgado'],
            'link_expediente'   => $row['link_expediente_digital'],
            'ubicacion_drive'   => $row['ubicacion_en_drive'],
            'observaciones'     => $row['observaciones'],

            // Conversión de fechas de Excel
            'fecha_radicado'         => $this->transformDate($row['fecha_radicado']),
            'fecha_revision'         => $this->transformDate($row['fecha_de_revision']),
            'fecha_proxima_revision' => $this->transformDate($row['fecha_de_proxima_revision_revision']),

            // Asignación de IDs de las relaciones
            'abogado_id'              => $abogado->id ?? null,
            'responsable_revision_id' => $responsable->id ?? null,
            'juzgado_id'              => $juzgado->id,
            'tipo_proceso_id'         => $tipoProceso->id,
            'demandante_id'           => $demandante->id,
            'demandado_id'            => $demandado->id,
            
            // Asigna el ID del usuario que está realizando la importación
            'created_by'              => Auth::id(),
        ]);
    }

    /**
     * Define las reglas de validación para cada fila del Excel.
     */
    public function rules(): array
    {
        return [
            'radicado' => 'required|string|unique:proceso_radicados,radicado',
            'tipo_de_proceso' => 'required|string',
            'demandante_denunciante' => 'required|string',
            'demandado_denunciado' => 'required|string',
            'juzgado_entidad' => 'required|string',
            // Añade más reglas si lo necesitas, por ejemplo, para las fechas:
            // 'fecha_radicado' => 'required',
        ];
    }

    /**
     * Define el tamaño del lote para procesar archivos grandes sin agotar la memoria.
     */
    public function chunkSize(): int
    {
        return 200; // Procesa el archivo en lotes de 200 filas a la vez
    }

    /**
     * Función auxiliar para transformar fechas de formato numérico de Excel a 'Y-m-d'.
     */
    private function transformDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }
        try {
            // Intenta convertir el número de serie de fecha de Excel a un objeto DateTime
            return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('Y-m-d');
        } catch (\Exception $e) {
            // Si falla (ej. ya es un texto como '25/12/2025'), intenta parsearlo de forma estándar
            try {
                return date('Y-m-d', strtotime($value));
            } catch (\Exception $ex) {
                return null; // Si ambos métodos fallan, devuelve null
            }
        }
    }
}
