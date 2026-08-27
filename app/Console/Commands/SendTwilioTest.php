<?php

namespace App\Console\Commands;

use App\Services\TwilioMessagingService;
use Illuminate\Console\Command;
use Throwable;

class SendTwilioTest extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'app:send-twilio-test
                            {number : Número destinatario en formato E.164, por ejemplo +573001234567}
                            {--media-url= : URL HTTPS pública para probar un mensaje multimedia}';

    /**
     * The console command description.
     */
    protected $description = 'Envía un SMS o mensaje multimedia de prueba mediante Twilio.';

    /**
     * Execute the console command.
     */
    public function handle(TwilioMessagingService $twilio): int
    {
        $number = (string) $this->argument('number');
        $mediaUrl = $this->option('media-url');

        try {
            $response = $twilio->send(
                $number,
                'Hola, esta es una prueba de conexión desde el sistema con Twilio.',
                is_string($mediaUrl) && $mediaUrl !== '' ? $mediaUrl : null
            );
        } catch (Throwable $exception) {
            $this->error('No fue posible enviar el mensaje de prueba: '.$exception->getMessage());

            return self::FAILURE;
        }

        $status = (string) ($response->json('status') ?? 'aceptado');

        $this->info("Twilio aceptó el mensaje de prueba. Estado inicial: {$status}.");

        return self::SUCCESS;
    }
}
