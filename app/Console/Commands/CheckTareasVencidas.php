<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tarea;
use App\Models\User;
use App\Notifications\TareaVencidaAdmin;
use Illuminate\Support\Facades\Log;

class CheckTareasVencidas extends Command
{
    /**
     * El nombre del comando.
     */
    protected $signature = 'tareas:check-vencidas';

    /**
     * Descripción.
     */
    protected $description = 'Revisa tareas vencidas y acusa a los usuarios con el admin.';

    /**
     * Ejecución.
     */
    public function handle()
    {
        $this->info('Iniciando la inquisición de tareas vencidas...');

        // 1. Buscar tareas que:
        // - Estén pendientes
        // - Su fecha límite ya pasó (fecha_limite < NOW())
        // - No hayamos notificado aún al admin (aviso_vencimiento_enviado = false)
        
        $tareasVencidas = Tarea::where('estado', 'pendiente')
            ->where('fecha_limite', '<', now())
            ->where('aviso_vencimiento_enviado', false)
            ->with(['creadaPor', 'asignadoA']) // Traemos las relaciones
            ->get();

        if ($tareasVencidas->isEmpty()) {
            $this->info('Todo en orden. Nadie está en problemas (por ahora).');
            return;
        }

        foreach ($tareasVencidas as $tarea) {
            // El admin que creó la tarea es quien recibe la queja
            $admin = $tarea->creadaPor; 
            
            // Si el admin ya no existe (raro), buscamos al primer admin del sistema
            if (!$admin) {
                $admin = User::where('tipo_usuario', 'admin')->first();
            }

            if ($admin) {
                try {
                    $this->info("Reportando tarea ID {$tarea->id} de {$tarea->asignadoA->name} al admin {$admin->name}...");
                    
                    // Enviar notificación
                    $admin->notify(new TareaVencidaAdmin($tarea));
                    
                    // Marcar como notificada para no spamear
                    $tarea->aviso_vencimiento_enviado = true;
                    $tarea->save();

                } catch (\Exception $e) {
                    Log::error("Error notificando vencimiento tarea {$tarea->id}: " . $e->getMessage());
                }
            }
        }

        $this->info('Inquisición finalizada.');
    }
}