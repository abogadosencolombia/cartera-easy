<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TipoProceso;
use App\Models\SubtipoProceso;

class SubtiposProcesoSeeder extends Seeder
{
    public function run(): void
    {
        // Asegurar tipos base presentes
        //uwu
        $tipoId = fn(string $n) => TipoProceso::firstOrCreate(['nombre' => $n])->id;

        $map = [
            'EJECUTIVO' => ['HIPOTECARIO', 'MIXTO', 'PRENDARIO', 'SINGULAR', 'GARANTIA REAL'],
            'INSOLVENCIA ECONOMICA' => ['PERSONA NATURAL'],
            'LABORAL' => ['LABORAL'],
            'RESTITUCION' => ['INMUEBLE', "MUEBLE"],
            'PAGO DIRECTO' => ['PAGO DIRECTO'],
            'REGIMEN DE INSOLVENCIA' => ['PERSONA JURIDICA', 'PERSONA NATURAL COMERCIANTE'],
            'PROCESO VERBAL' => ['VERBAL'],
            'PERSONAL' => ['PERSONAL'],
            'CURADURIA' => ['CURADURIA'],
            // Puedes añadir más mapeos aquí cuando lo requieras
            //o darle Ctrl + Z por que no creo que funcione, pero si quiere lo deja asi, no se jaja, por que mera cosa eso ahi pa, 
        ];

        foreach ($map as $tipoNombre => $subtipos) {
            $tId = $tipoId($tipoNombre);
            foreach ($subtipos as $nombre) {
                SubtipoProceso::firstOrCreate(
                    ['tipo_proceso_id' => $tId, 'nombre' => $nombre],
                    []
                );
            }
        }
    }
}
