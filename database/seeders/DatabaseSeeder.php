<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // --- SEEDERS DE PRODUCCIÓN (Esenciales) ---
        // Estos se ejecutarán SIEMPRE, tanto en tu computadora como en el servidor.
        // Contienen los catálogos y datos necesarios para que la app funcione.
        $this->call([
            IntegracionTokenSeeder::class,
            EspecialidadesJuridicasSeeder::class,
            TiposProcesoSeeder::class,
            EtapasProcesalesSeeder::class,
            SubtiposProcesoSeeder::class,
            JuzgadoSeeder::class,
            EspecialidadSeeder::class,
        ]);

        // --- SEEDERS DE DESARROLLO (Datos de Prueba) ---
        // Este bloque solo se ejecutará en tu computadora (entorno 'local').
        // El servidor de Hostinger lo ignorará por completo, evitando el error de Faker.
        if (app()->environment('local')) {
            // Aquí van todos los llamados a factories para generar datos de prueba.
            User::factory(10)->create();

            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }
    }
}