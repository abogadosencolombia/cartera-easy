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
        $this->call([
            IntegracionTokenSeeder::class,
            
            // ¡ESTE ES EL ÚNICO SEEDER DE ESTRUCTURA QUE NECESITAMOS!
            // Él ya crea las especialidades, tipos, subtipos y subprocesos.
            EstructuraProcesalSeeder::class, 
            
            EtapasProcesalesSeeder::class,
            JuzgadoSeeder::class,
            
            // Los siguientes seeders son redundantes si 'EstructuraProcesalSeeder'
            // ya maneja la creación de estos datos. Los comento para evitar conflictos.
            // TiposProcesoSeeder::class,
            // SubtiposProcesoSeeder::class,
            // EspecialidadSeeder::class, 
        ]);

        // --- SEEDERS DE DESARROLLO (Datos de Prueba) ---
        if (app()->environment('local')) {
            // Aquí van todos los llamados a factories para generar datos de prueba.
            // User::factory(10)->create(); // Comentado para no crear usuarios extra por defecto

            // Asegurarse de que el usuario de prueba exista si no estamos en producción
            if (!User::where('email', 'test@example.com')->exists()) {
                 User::factory()->create([
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                ]);
            }
        }
    }
}