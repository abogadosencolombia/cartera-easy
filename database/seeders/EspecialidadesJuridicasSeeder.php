<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EspecialidadJuridica;

class EspecialidadesJuridicasSeeder extends Seeder
{
    public function run(): void
    {
        $lista = [
            'Civil','Penal','Laboral','Familia','Administrativo','Constitucional',
            'Comercial','Tributario','Migratorio','Disciplinario','Policivo',
            'Notarial','Minero-Energético','Ambiental','Consumo',
            'Propiedad Intelectual','Seguridad Social','Responsabilidad Médica',
            'Habeas Data / TI','Cobro de Cartera'
        ];

        foreach ($lista as $nombre) {
            EspecialidadJuridica::firstOrCreate(['nombre' => $nombre]);
        }
    }
}
