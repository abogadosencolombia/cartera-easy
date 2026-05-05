<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ReglaAlerta;
use App\Models\Contrato;
use App\Models\ProcesoRadicado;
use App\Models\User;
use App\Models\AuditoriaEvento;
use App\Notifications\AlertaAutomaticaNotification;
use Illuminate\Support\Facades\Notification;
use Carbon\Carbon;

class EjecutarAlertasAutomaticas extends Command
{
    /**
     * El nombre técnico del comando.
     */
    protected $signature = 'alertas:ejecutar';

    /**
     * La descripción.
     */
    protected $description = 'Revisa las reglas configuradas y envía notificaciones automáticas.';

    /**
     * Ejecución del comando.
     */
    public function handle()
    {
        $this->info('Iniciando motor de alertas...');
        $contadorAlertas = 0;

        // 1. Obtener todas las reglas activas
        $reglas = ReglaAlerta::with('cooperativa')->get();

        foreach ($reglas as $regla) {
            $this->info("Procesando regla: {$regla->tipo} ({$regla->dias} días) para {$regla->cooperativa->nombre}");

            // Buscamos los usuarios interesados (Admins y Gestores de esa cooperativa)
            // Nota: Simplificado para enviar a admins. Puedes refinar esto según tu lógica de asignación.
            $destinatarios = User::where('tipo_usuario', 'admin')->get(); 

            switch ($regla->tipo) {
                // CASO 1: MORA (Contratos con cuotas vencidas hace X días)
                case 'mora':
                    $fechaLimite = Carbon::now()->subDays($regla->dias)->toDateString();
                    
                    // Buscar contratos de esa cooperativa con cuotas vencidas más antiguas que la fecha límite
                    $contratosEnMora = Contrato::whereHas('cliente.cooperativas', function($q) use ($regla) {
                            $q->where('cooperativas.id', $regla->cooperativa_id);
                        })
                        ->whereHas('cuotas', function($q) use ($fechaLimite) {
                            $q->where('estado', 'PENDIENTE')
                              ->where('fecha_vencimiento', '<=', $fechaLimite);
                        })
                        ->with('cliente')
                        ->get();

                    foreach ($contratosEnMora as $contrato) {
                        $datos = [
                            'titulo' => "Mora detectada (> {$regla->dias} días)",
                            'mensaje' => "El contrato #{$contrato->id} del cliente {$contrato->cliente->nombre_completo} tiene cuotas con más de {$regla->dias} días de retraso.",
                            'url' => route('honorarios.contratos.show', $contrato->id),
                            'tipo' => 'mora'
                        ];
                        Notification::send($destinatarios, new AlertaAutomaticaNotification($datos));
                        $contadorAlertas++;
                    }
                    break;

                // CASO 2: INACTIVIDAD (Procesos sin actuaciones recientes)
                case 'inactividad':
                    $fechaLimite = Carbon::now()->subDays($regla->dias);

                    $procesosInactivos = ProcesoRadicado::whereHas('demandantes.cooperativas', function($q) use ($regla) {
                            $q->where('cooperativas.id', $regla->cooperativa_id);
                        })
                        ->paraSeguimiento()
                        ->where(function($q) use ($fechaLimite) {
                            // Sin actuaciones recientes O creado hace mucho sin actuaciones
                            $q->whereDoesntHave('actuaciones', function($sq) use ($fechaLimite) {
                                $sq->where('fecha_actuacion', '>=', $fechaLimite);
                            });
                        })
                        ->get();

                    foreach ($procesosInactivos as $proceso) {
                        $datos = [
                            'titulo' => "Proceso Inactivo (> {$regla->dias} días)",
                            'mensaje' => "El radicado {$proceso->radicado} no ha tenido actuaciones registradas en los últimos {$regla->dias} días.",
                            'url' => route('procesos.show', $proceso->id),
                            'tipo' => 'inactividad'
                        ];
                        Notification::send($destinatarios, new AlertaAutomaticaNotification($datos));
                        $contadorAlertas++;
                    }
                    break;

                // CASO 3: VENCIMIENTO (Etapas procesales o revisiones próximas)
                case 'vencimiento':
                    // Avisar X días ANTES de la próxima revisión
                    $fechaObjetivo = Carbon::now()->addDays($regla->dias)->toDateString();

                    $procesosPorVencer = ProcesoRadicado::whereHas('demandantes.cooperativas', function($q) use ($regla) {
                            $q->where('cooperativas.id', $regla->cooperativa_id);
                        })
                        ->paraSeguimiento()
                        ->whereDate('fecha_proxima_revision', '=', $fechaObjetivo)
                        ->get();

                    foreach ($procesosPorVencer as $proceso) {
                        $datos = [
                            'titulo' => "Próxima Revisión en {$regla->dias} días",
                            'mensaje' => "El radicado {$proceso->radicado} requiere revisión el día " . $proceso->fecha_proxima_revision,
                            'url' => route('procesos.show', $proceso->id),
                            'tipo' => 'vencimiento'
                        ];
                        Notification::send($destinatarios, new AlertaAutomaticaNotification($datos));
                        $contadorAlertas++;
                    }
                    break;
            }
        }

        // ✅ REGISTRAR EN AUDITORÍA GLOBAL (Usuario 0 o 1 suele ser Sistema)
        // Usamos un ID de usuario existente o null si la tabla lo permite, 
        // o el primer admin para que conste.
        $sistemaId = User::where('tipo_usuario', 'admin')->first()->id ?? 1;

        if ($contadorAlertas > 0) {
            AuditoriaEvento::create([
                'user_id' => $sistemaId, 
                'evento' => 'EJECUCION_AUTOMATICA_ALERTAS',
                'descripcion_breve' => "El sistema ejecutó las reglas y envió {$contadorAlertas} notificaciones.",
                'criticidad' => 'baja',
                'direccion_ip' => '127.0.0.1',
                'user_agent' => 'System/Console',
            ]);
        }

        $this->info("Proceso finalizado. Se enviaron {$contadorAlertas} alertas.");
    }
}
