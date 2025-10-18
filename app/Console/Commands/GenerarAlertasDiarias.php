<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ReglaAlerta;
use App\Models\Caso;
use App\Models\NotificacionCaso;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Mail\NotificacionCasoMail;
use Illuminate\Support\Facades\Mail;

class GenerarAlertasDiarias extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'alertas:generar';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genera notificaciones diarias basadas en las reglas de alerta activas.';

    /**
     * @var \Illuminate\Support\Collection
     */
    private $adminUserIds;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando la Operación: Guardián Proactivo...');
        
        // Obtenemos los IDs de los administradores una sola vez para optimizar.
        $this->adminUserIds = User::where('tipo_usuario', 'admin')->pluck('id');

        $reglas = ReglaAlerta::where('activa', true)->get();

        if ($reglas->isEmpty()) {
            $this->warn('No hay reglas de alerta activas. Misión abortada.');
            return;
        }

        foreach ($reglas as $regla) {
            $this->line("Procesando regla: [{$regla->tipo}] para Cooperativa [{$regla->cooperativa->nombre}] a los [{$regla->dias}] días.");
            
            switch ($regla->tipo) {
                case 'mora':
                    $this->procesarAlertasDeMora($regla);
                    break;
                case 'vencimiento':
                    $this->procesarAlertasDeVencimiento($regla);
                    break;
                case 'inactividad':
                    $this->procesarAlertasDeInactividad($regla);
                    break;
                case 'documento_faltante':
                    $this->procesarAlertasDeDocumentosFaltantes($regla);
                    break;
            }
        }

        $this->info('Operación: Guardián Proactivo completada.');
    }

    private function procesarAlertasDeMora(ReglaAlerta $regla)
    {
        $fechaMora = Carbon::today()->subDays($regla->dias)->toDateString();
        $casosAfectados = Caso::where('cooperativa_id', $regla->cooperativa_id)
            ->where('estado_proceso', '!=', 'cerrado')
            ->whereDate('fecha_vencimiento', $fechaMora)
            ->get();

        foreach ($casosAfectados as $caso) {
            $this->crearNotificaciones($caso, $regla, 'alta', 
                "Mora Crítica: El caso #{$caso->id} ({$caso->nombre_caso}) ha alcanzado {$regla->dias} días en mora."
            );
        }
    }

    private function procesarAlertasDeVencimiento(ReglaAlerta $regla)
    {
        $fechaVencimiento = Carbon::today()->addDays($regla->dias)->toDateString();
        $casosAfectados = Caso::where('cooperativa_id', $regla->cooperativa_id)
            ->where('estado_proceso', '!=', 'cerrado')
            ->whereDate('fecha_vencimiento', $fechaVencimiento)
            ->get();

        foreach ($casosAfectados as $caso) {
            $this->crearNotificaciones($caso, $regla, 'media',
                "Alerta de Vencimiento: El caso #{$caso->id} ({$caso->nombre_caso}) vencerá en {$regla->dias} días."
            );
        }
    }

    private function procesarAlertasDeInactividad(ReglaAlerta $regla)
    {
        $fechaInactividad = Carbon::today()->subDays($regla->dias);
        $casosAfectados = Caso::where('cooperativa_id', $regla->cooperativa_id)
            ->where('estado_proceso', '!=', 'cerrado')
            ->where('updated_at', '<=', $fechaInactividad)
            ->get();

        foreach ($casosAfectados as $caso) {
            $this->crearNotificaciones($caso, $regla, 'media',
                "Alerta de Inactividad: El caso #{$caso->id} ({$caso->nombre_caso}) no ha tenido movimiento en {$regla->dias} o más días."
            );
        }
    }

    private function procesarAlertasDeDocumentosFaltantes(ReglaAlerta $regla)
    {
        // Esta lógica puede ser compleja, asumimos que está correcta como la tenías.
        $casosAfectados = Caso::where('cooperativa_id', $regla->cooperativa_id)
            ->where('estado_proceso', '!=', 'cerrado')
            // ... (tu lógica para encontrar documentos faltantes)
            ->get();

        foreach ($casosAfectados as $caso) {
            $this->crearNotificaciones($caso, $regla, 'baja',
                "Riesgo Documental: El caso #{$caso->id} ({$caso->nombre_caso}) presenta documentos faltantes."
            );
        }
    }

    /**
     * --- MÉTODO ACTUALIZADO PARA CREAR Y ENVIAR NOTIFICACIONES ---
     */
    private function crearNotificaciones(Caso $caso, ReglaAlerta $regla, string $prioridad, string $mensaje)
    {
        $destinatarios = collect();

        if ($caso->user_id) {
            $destinatarios->push($caso->user_id);
        }
        $destinatarios = $destinatarios->merge($this->adminUserIds)->unique();

        foreach ($destinatarios as $userId) {
            $notificacionExistente = NotificacionCaso::where('caso_id', $caso->id)
                ->where('user_id', $userId)
                ->where('tipo', $regla->tipo)
                ->where('leido', false)
                ->exists();

            if (!$notificacionExistente) {
                $nuevaNotificacion = NotificacionCaso::create([
                    'caso_id' => $caso->id,
                    'user_id' => $userId,
                    'tipo' => $regla->tipo,
                    'mensaje' => $mensaje,
                    'prioridad' => $prioridad,
                    'fecha_envio' => now(),
                ]);
                
                $this->info("-> Notificación [{$regla->tipo}] creada para el caso #{$caso->id} para el usuario #{$userId}.");

                $destinatario = User::find($userId);

                if ($destinatario) {
                    $preferencias = $destinatario->preferencias_notificacion ?? [];
                    
                    if (isset($preferencias['email']) && $preferencias['email'] === true) {
                        
                        // ===== AQUÍ LA MEJORA 1 =====
                        // Cargamos la relación con el 'caso' para que sus datos (como el nombre)
                        // estén disponibles para la plantilla del correo.
                        $nuevaNotificacion->load('caso');

                        Mail::to($destinatario->email)->send(new NotificacionCasoMail($nuevaNotificacion));
                        
                        // Mensaje de log corregido para que no diga "encolado"
                        $this->info("   -> Enviando correo de notificación para {$destinatario->email}.");
                    }
                }
            } else {
                // ===== AQUÍ LA MEJORA 2 =====
                // Mensaje de log corregido para no causar errores de variable indefinida.
                $this->comment("-> Notificación [{$regla->tipo}] para el caso #{$caso->id} (usuario #{$userId}) ya existe y está pendiente. Omitiendo.");
            }
        }
    }
}