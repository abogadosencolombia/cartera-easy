<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Especialidad;
use Illuminate\Support\Facades\DB;

class EspecialidadSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Vaciamos la tabla.
        // En PostgreSQL, usamos CASCADE para que limpie también las referencias en la tabla pivote (especialidad_user)
        // sin necesidad de desactivar las foreign keys globalmente.
        DB::table('especialidades')->truncate(); 
        // Si te sigue dando error, cambia la línea de arriba por:
        // DB::statement('TRUNCATE TABLE especialidades CASCADE;');

        $especialidades = [
            // Derecho Público
            ['nombre' => 'DERECHO ADMINISTRATIVO'],
            ['nombre' => 'DERECHO CONSTITUCIONAL'],
            ['nombre' => 'DERECHO TRIBUTARIO Y ADUANERO'],
            ['nombre' => 'CONTRATACIÓN ESTATAL'],
            ['nombre' => 'DERECHO URBANO E INMOBILIARIO'],
            ['nombre' => 'DERECHO AMBIENTAL Y MINERO'],
            ['nombre' => 'SERVICIOS PÚBLICOS DOMICILIARIOS'],
            ['nombre' => 'DERECHO DISCIPLINARIO'],
            ['nombre' => 'RESPONSABILIDAD FISCAL'],

            // Derecho Privado
            ['nombre' => 'DERECHO CIVIL'],
            ['nombre' => 'DERECHO COMERCIAL Y DE LA EMPRESA'],
            ['nombre' => 'DERECHO DE SOCIEDADES'],
            ['nombre' => 'DERECHO FINANCIERO Y BURSÁTIL'],
            ['nombre' => 'PROPIEDAD INTELECTUAL Y NUEVAS TECNOLOGÍAS'],
            ['nombre' => 'DERECHO DE SEGUROS'],
            ['nombre' => 'DERECHO DE LA COMPETENCIA Y DEL CONSUMO'],
            ['nombre' => 'INSOLVENCIA Y REORGANIZACIÓN EMPRESARIAL'],

            // Derecho Penal
            ['nombre' => 'DERECHO PENAL'],
            ['nombre' => 'DERECHO PENAL ECONÓMICO Y COMPLIANCE'],
            ['nombre' => 'CRIMINALÍSTICA Y CIENCIAS FORENSES'],

            // Derecho Laboral y Seguridad Social
            ['nombre' => 'DERECHO LABORAL INDIVIDUAL Y COLECTIVO'],
            ['nombre' => 'DERECHO DE LA SEGURIDAD SOCIAL'],

            // Derecho de Familia y Sucesiones
            ['nombre' => 'DERECHO DE FAMILIA'],
            ['nombre' => 'DERECHO DE SUCESIONES'],
            ['nombre' => 'VIOLENCIA INTRAFAMILIAR'],

            // Otras áreas especializadas
            ['nombre' => 'DERECHO PROCESAL'],
            ['nombre' => 'ARBITRAJE, CONCILIACIÓN Y MASC'],
            ['nombre' => 'DERECHO MÉDICO Y DE LA SALUD'],
            ['nombre' => 'DERECHO INTERNACIONAL Y D.I.H.'],
            ['nombre' => 'DERECHO DE TRÁNSITO Y TRANSPORTE'],
            ['nombre' => 'DERECHO CANÓNICO'],
            ['nombre' => 'DERECHOS HUMANOS Y DESPLAZAMIENTO'],
            ['nombre' => 'DERECHO AGRARIO Y DE TIERRAS'],
            ['nombre' => 'PROPIEDAD HORIZONTAL'],
            ['nombre' => 'DERECHO NOTARIAL Y REGISTRAL'],
            ['nombre' => 'PROTECCIÓN DE DATOS (HABEAS DATA)'],
            ['nombre' => 'DERECHO POLICIVO'],
        ];

        // 2. Agregamos timestamps
        $now = now();
        $dataWithTimestamps = array_map(function ($item) use ($now) {
            return array_merge($item, [
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }, $especialidades);

        // 3. Insertamos
        Especialidad::insert($dataWithTimestamps);
    }
}