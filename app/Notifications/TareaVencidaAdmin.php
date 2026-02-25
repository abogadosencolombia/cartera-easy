<?php

namespace App\Notifications;

use App\Models\Tarea;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TareaVencidaAdmin extends Notification
{
    use Queueable;

    public Tarea $tarea;

    public function __construct(Tarea $tarea)
    {
        $this->tarea = $tarea;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'icon' => 'warning', // Un icono de alerta
            'title' => '¡TAREA VENCIDA!',
            'message' => "El usuario {$this->tarea->asignadoA->name} no cumplió con: '{$this->tarea->titulo}'",
            'description' => "La fecha límite era: " . $this->tarea->fecha_limite->format('d/m/Y h:i A'),
            'details' => "Vencimiento",
            'link' => '/admin/tareas', // Lleva al panel de admin
            'tarea_id' => $this->tarea->id
        ];
    }
}