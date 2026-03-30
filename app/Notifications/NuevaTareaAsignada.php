<?php

namespace App\Notifications;

use App\Models\Tarea;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NuevaTareaAsignada extends Notification 
{
    use Queueable;

    public Tarea $tarea;

    /**
     * Create a new notification instance.
     */
    public function __construct(Tarea $tarea)
    {
        $this->tarea = $tarea;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        // Solo base de datos para que aparezca en la campana
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        // 1. Valores por defecto (Caso: Nota General / Tarea Suelta)
        $link = '#'; 
        $tipo = 'Nota General';
        $detalleVinculo = '';
        
        $tareaType = $this->tarea->tarea_type;
        $this->tarea->load('tarea'); // Cargar la entidad vinculada (proceso, caso o contrato)
        $entidad = $this->tarea->tarea;

        // 2. Si hay vinculación, calculamos el link y el nombre del tipo
        if ($tareaType && $entidad) {
            if ($tareaType === 'App\Models\ProcesoRadicado' || $tareaType === 'proceso') {
                $link = route('procesos.show', $this->tarea->tarea_id);
                $tipo = 'Proceso/Radicado';
                $detalleVinculo = $entidad->radicado ?? 'ID: ' . $entidad->id;
            } elseif ($tareaType === 'App\Models\Caso' || $tareaType === 'caso') {
                $link = route('casos.show', $this->tarea->tarea_id);
                $tipo = 'Caso';
                $detalleVinculo = $entidad->asunto ?? 'ID: ' . $entidad->id;
            } elseif ($tareaType === 'App\Models\Contrato' || $tareaType === 'contrato') {
                $link = route('honorarios.contratos.show', $this->tarea->tarea_id);
                $tipo = 'Contrato de Honorarios';
                $detalleVinculo = 'N° ' . ($entidad->id ?? '');
            }
        }

        $mensaje = "Se te asignó la tarea: '{$this->tarea->titulo}'";
        if ($detalleVinculo) {
            $mensaje .= " ({$tipo}: {$detalleVinculo})";
        }

        return [
            'icon' => 'task',
            'title' => 'Nueva Tarea Asignada',
            'message' => $mensaje,
            'description' => $this->tarea->description ?? $this->tarea->descripcion,
            
            // 3. Manejo seguro de la fecha límite (puede ser null)
            'deadline' => $this->tarea->fecha_limite ? $this->tarea->fecha_limite->format('d/m/Y h:i A') : null,
            
            // 4. Detalle dinámico
            'details' => $entidad ? "Vinculada a {$tipo}: {$detalleVinculo}" : "Tarea General",
            
            'link' => $link,
            'tarea_id' => $this->tarea->id
        ];
    }
}