<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Caso;
use App\Models\ValidacionLegal;
use Illuminate\Support\Carbon;

class DebugCumplimientoCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'debug:cumplimiento {caso_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ejecuta y depura la lógica de cumplimiento legal para un caso específico o para todos.';

    private const RIESGO_POR_TIPO = [
        'poder_vencido' => 'alto',
        'tasa_usura' => 'alto',
        'plazo_excedido_sin_demanda' => 'alto',
        'sin_pagare' => 'medio',
        'sin_carta_instrucciones' => 'medio',
        'tipo_proceso_vs_garantia' => 'medio',
        'sin_certificacion_saldo' => 'bajo',
        'documento_faltante_para_radicar' => 'bajo',
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('************************************************');
        $this->info('*** INICIANDO DEPURACIÓN DE CUMPLIMIENTO LEGAL ***');

        $casoId = $this->argument('caso_id');
        $query = Caso::whereIn('estado', ['activo', 'prejuridico']);

        if ($casoId) {
            $this->line("-> Buscando caso específico con ID: {$casoId}");
            $query->where('id', $casoId);
        } else {
            $this->line("-> Buscando todos los casos con estado 'activo' o 'prejuridico'.");
        }

        $casos = $query->with(['cooperativa.configuracionLegal', 'documentos', 'deudor'])->get();

        $this->info("-> Se encontraron {$casos->count()} casos para procesar.");

        if ($casos->isEmpty()) {
            $this->warn('ADVERTENCIA: No se encontraron casos que coincidan con los criterios. Finalizando.');
            return 0;
        }

        foreach ($casos as $caso) {
            $this->line(''); // New line
            $this->info("--- Procesando Caso #{$caso->id} (Deudor: ".($caso->deudor->nombre_completo ?? 'N/A').") ---");

            if (!$caso->cooperativa) {
                $this->error("ERROR FATAL: El Caso #{$caso->id} no tiene una cooperativa asignada. Saltando...");
                continue;
            }
            $this->info("   [OK] Cooperativa encontrada: {$caso->cooperativa->nombre}");

            $config = $caso->cooperativa->configuracionLegal;
            if (!$config) {
                $this->error("ERROR FATAL: La cooperativa '{$caso->cooperativa->nombre}' no tiene un registro en 'configuraciones_legales'. Saltando...");
                continue;
            }
            $this->info("   [OK] Configuración legal encontrada.");

            // Llamadas a las verificaciones
            $this->verificarPlazoDemanda($caso, $config);
            $this->verificarDocumento($caso, $config, 'sin_pagare', 'pagare', 'exige_pagare');
            $this->verificarDocumento($caso, $config, 'sin_carta_instrucciones', 'carta instrucciones', 'exige_carta_instrucciones');
            $this->verificarDocumento($caso, $config, 'sin_certificacion_saldo', 'certificacion saldo', 'exige_certificacion_saldo');
        }

        $this->info('************************************************');
        $this->info('*** DEPURACIÓN FINALIZADA ***');
        $this->comment('Por favor, revisa la ficha del caso en el navegador para ver los resultados.');
        return 0;
    }

    private function verificarPlazoDemanda(Caso $caso, $config)
    {
        $this->comment("   -> Verificando 'Plazo para Demandar'...");
        if (!$caso->fecha_asignacion) {
            $this->warn("      Lógica Saltada: El campo 'fecha_asignacion' es nulo para este caso.");
            return;
        }
        $this->info("      Fecha de asignación encontrada: {$caso->fecha_asignacion}");
        
        $diasDesdeAsignacion = Carbon::now()->diffInDays($caso->fecha_asignacion);
        $this->info("      Días transcurridos: {$diasDesdeAsignacion}. Límite: {$config->dias_maximo_para_demandar} días.");
        
        $estado = ($diasDesdeAsignacion > $config->dias_maximo_para_demandar) ? 'incumple' : 'cumple';
        $this->info("      => RESULTADO: {$estado}");
        
        $this->actualizarValidacion($caso, 'plazo_excedido_sin_demanda', $estado, "Días transcurridos: {$diasDesdeAsignacion}");
    }

    private function verificarDocumento(Caso $caso, $config, string $tipoValidacion, string $tipoDocEnBD, string $campoConfig)
    {
        $this->comment("   -> Verificando documento '{$tipoDocEnBD}'...");
        if (isset($config->$campoConfig) && $config->$campoConfig) {
            $this->info("      La cooperativa exige este documento.");
            $tieneDocumento = $caso->documentos()->where('tipo_documento', $tipoDocEnBD)->exists();
            $estado = $tieneDocumento ? 'cumple' : 'incumple';
            $this->info("      => RESULTADO: {$estado}");
            $this->actualizarValidacion($caso, $tipoValidacion, $estado, $tieneDocumento ? 'Documento encontrado.' : 'Documento faltante.');
        } else {
            $estado = 'no_aplica';
            $this->info("      La cooperativa NO exige este documento.");
            $this->info("      => RESULTADO: {$estado}");
            $this->actualizarValidacion($caso, $tipoValidacion, $estado, 'No exigido por la cooperativa.');
        }
    }

    private function actualizarValidacion(Caso $caso, string $tipo, string $estado, string $observacion): void
    {
        ValidacionLegal::updateOrCreate(
            ['caso_id' => $caso->id, 'tipo' => $tipo],
            [
                'estado' => $estado,
                'observacion' => $observacion,
                'ultima_revision' => now(),
                'nivel_riesgo' => self::RIESGO_POR_TIPO[$tipo] ?? 'medio',
            ]
        );
    }
}
