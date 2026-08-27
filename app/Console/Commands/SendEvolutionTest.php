<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

class SendEvolutionTest extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'app:send-evolution-test';

    /**
     * The console command description.
     */
    protected $description = 'Envía un mensaje de prueba de WhatsApp mediante Evolution API.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! env('EVOLUTION_API_URL') || ! env('EVOLUTION_INSTANCE') || ! env('EVOLUTION_API_KEY')) {
            $this->error(
                'Debes configurar EVOLUTION_API_URL, EVOLUTION_INSTANCE y EVOLUTION_API_KEY en el archivo .env.'
            );

            return self::FAILURE;
        }

        $body = [
            // Número de prueba autorizado, con indicativo 57 y sin el signo +.
            'number' => '573152819233',
            'text' => 'Hola, esta es una prueba de conexión desde el sistema con Evolution API.',
        ];

        if ($body['number'] === '57XXXXXXXXXX') {
            $this->error(
                'Reemplaza 57XXXXXXXXXX por tu número real en app/Console/Commands/SendEvolutionTest.php.'
            );

            return self::FAILURE;
        }

        if (! preg_match('/^57\d{10}$/', $body['number'])) {
            $this->error('El número debe tener el formato 57 seguido de los 10 dígitos del celular, sin espacios ni signo +.');

            return self::FAILURE;
        }

        $url = env('EVOLUTION_API_URL').'/message/sendText/'.env('EVOLUTION_INSTANCE');

        try {
            $response = Http::withHeaders([
                'apikey' => env('EVOLUTION_API_KEY'),
                'Content-Type' => 'application/json',
            ])->timeout(20)->post($url, $body);
        } catch (Throwable $exception) {
            $this->error('No fue posible conectarse con Evolution API: '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($response->failed()) {
            $this->error("Evolution API respondió con HTTP {$response->status()}.");
            $this->line($response->body());

            return self::FAILURE;
        }

        $this->info('Mensaje de prueba enviado correctamente mediante Evolution API.');

        return self::SUCCESS;
    }
}
