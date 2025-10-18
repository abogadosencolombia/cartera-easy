<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\IntegracionToken;

class IntegracionTokenSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Creamos un registro de prueba para nuestro simulador usando updateOrCreate
        // para evitar duplicados en caso de ejecutar el seeder múltiples veces
        IntegracionToken::updateOrCreate(
            ['proveedor' => 'Supersolidaria (Simulador)'], // Condición de búsqueda
            [
                'api_key' => 'ESTA_ES_UNA_API_KEY_DE_PRUEBA_12345',
                'activo' => true,
            ]
        );

        // Aquí podrías añadir en el futuro las credenciales para CIFIN, WhatsApp, etc.
        /*
        IntegracionToken::updateOrCreate(
            ['proveedor' => 'CIFIN'],
            [
                'client_id' => '...',
                'client_secret' => '...',
                'activo' => false, // Lo dejamos inactivo hasta que esté listo
            ]
        );
        
        IntegracionToken::updateOrCreate(
            ['proveedor' => 'WhatsApp'],
            [
                'api_key' => '...',
                'activo' => false,
            ]
        );
        */
        
        $this->command->info('Tokens de integración creados/actualizados correctamente.');
    }
}