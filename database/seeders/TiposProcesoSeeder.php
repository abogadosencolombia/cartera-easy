<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TipoProceso;

class TiposProcesoSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            'CURADURIA',
            'EJECUTIVO',
            'RESTITUCION',
            'LABORAL',
            'PAGO DIRECTO',
            'REGIMEN DE INSOLVENCIA',
            'INSOLVENCIA ECONOMICA',
            'PERSONAL',
            'PROCESO VERBAL',
            'LIQUIDATORIO',
        	'DECLARATIVO',
        ];

        foreach ($tipos as $nombre) {
            TipoProceso::firstOrCreate(['nombre' => $nombre]);
        }
    }
}
