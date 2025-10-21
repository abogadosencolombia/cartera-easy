<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Cooperativa;
use App\Services\IntegrationService;
use Illuminate\Support\Facades\Log;

class ValidarCooperativasDiariamente extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cooperativas:validar-diariamente';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Valida el estado de todas las cooperativas con NIT contra un servicio externo.';

    /**
     * El servicio de integración que usaremos.
     *
     * @var IntegrationService
     */
    protected $integrationService;

    /**
     * Create a new command instance.
     *
     * @param IntegrationService $integrationService
     */
    public function __construct(IntegrationService $integrationService)
    {
        parent::__construct();
        $this->integrationService = $integrationService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Iniciando proceso de validación diaria de cooperativas...');
        Log::info('Iniciando Tarea Programada: ValidarCooperativasDiariamente.');

        $cooperativas = Cooperativa::whereNotNull('NIT')->where('NIT', '!=', '')->get();

        if ($cooperativas->isEmpty()) {
            $this->warn('No se encontraron cooperativas con NIT para validar. Proceso terminado.');
            Log::info('No se encontraron cooperativas con NIT para validar.');
            return 0;
        }

        $this->info("Se encontraron {$cooperativas->count()} cooperativas para validar.");
        
        $this->withProgressBar($cooperativas, function ($cooperativa) {
            
            // Escudo de protección para omitir NITs vacíos
            if (empty(trim($cooperativa->NIT))) {
                Log::warning("Omitiendo cooperativa '{$cooperativa->nombre}' (ID: {$cooperativa->id}) por tener un NIT vacío.");
                return; // Salta a la siguiente cooperativa
            }

            // Llamamos directamente al método de simulación del servicio,
            // que es más rápido y confiable que una llamada HTTP.
            $response = $this->integrationService->simularValidacionSupersolidaria($cooperativa->NIT);
            
            // Creamos un log manual para mantener la trazabilidad de la tarea programada.
            \App\Models\IntegracionExternaLog::create([
                'servicio' => 'Supersolidaria (Simulador) - Tarea Programada',
                'endpoint' => 'simulador/validacion-interna',
                'datos_enviados' => json_encode(['nit' => $cooperativa->NIT]),
                'respuesta' => json_encode($response),
                'user_id' => null, // No hay usuario en una tarea de consola
                'fecha_solicitud' => now(),
            ]);

            // Verificamos la respuesta
            if (isset($response['error']) && $response['error']) {
                Log::error("Fallo al validar la cooperativa {$cooperativa->nombre} (NIT: {$cooperativa->NIT}). Razón: " . $response['mensaje']);
            } else {
                $estado = $response['estado'] ?? 'Desconocido';
                Log::info("Validación exitosa para {$cooperativa->nombre}. Estado: {$estado}.");
                
                // Futuro: Aquí podrías actualizar el estado de la cooperativa en la BD.
                // $cooperativa->update(['estado_supersolidaria' => $estado]);
            }
        });

        $this->newLine(2);
        $this->info('✅ Proceso de validación diaria de cooperativas completado.');
        Log::info('Finalizada Tarea Programada: ValidarCooperativasDiariamente.');

        return 0;
    }
}
