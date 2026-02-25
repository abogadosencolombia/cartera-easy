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
        
        $tareaType = $this->tarea->tarea_type;

        // 2. Si hay vinculación, calculamos el link y el nombre del tipo
        if ($tareaType) {
            if ($tareaType === 'App\Models\ProcesoRadicado' || $tareaType === 'proceso') {
                $link = '/procesos/' . $this->tarea->tarea_id;
                $tipo = 'Proceso/Radicado';
            } elseif ($tareaType === 'App\Models\Caso' || $tareaType === 'caso') {
                $link = '/casos/' . $this->tarea->tarea_id;
                $tipo = 'Caso';
            } elseif ($tareaType === 'App\Models\Contrato' || $tareaType === 'contrato') {
                $link = '/gestion/honorarios/contratos/' . $this->tarea->tarea_id;
                $tipo = 'Contrato de Honorarios';
            }
        }

        return [
            'icon' => 'task',
            'title' => 'Nueva Tarea Asignada',
            'message' => "Se te asignó la tarea: '{$this->tarea->titulo}'",
            'description' => $this->tarea->descripcion,
            
            // 3. Manejo seguro de la fecha límite (puede ser null)
            // Si hay fecha, la formateamos. Si no, enviamos null.
            'deadline' => $this->tarea->fecha_limite ? $this->tarea->fecha_limite->format('d/m/Y h:i A') : null,
            
            // 4. Detalle dinámico
            'details' => $tareaType ? "Vinculada a: {$tipo}" : "Tarea General",
            
            'link' => $link,
            'tarea_id' => $this->tarea->id
        ];
    }
}