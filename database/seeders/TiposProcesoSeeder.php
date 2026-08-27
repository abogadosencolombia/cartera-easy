<?php

namespace Database\Seeders;

use App\Models\TipoProceso;
use Illuminate\Database\Seeder;

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
            'COMPRAVENTA',
            'RECUPERACIÓN DE VIDA CREDITICIA',
        ];

        foreach ($tipos as $nombre) {
            TipoProceso::firstOrCreate(['nombre' => $nombre]);
        }
    }
}
