<?php

namespace App\Notifications;

use App\Models\Tarea;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Contrato; // Usamos el modelo correcto


class NuevaTareaAsignada extends Notification implements ShouldQueue
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
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // La enviaremos solo a la base de datos (para que aparezca en la campana)
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        // Construimos el link al que irá el usuario
        $link = '#'; // Link por defecto
        $tipo = 'Elemento';

        // ===== INICIO DE LA MODIFICACIÓN (FIX CORS) =====
        // Creamos enlaces relativos en lugar de absolutos
        if ($this->tarea->tarea_type === 'App\Models\ProcesoRadicado') {
            $link = '/procesos/' . $this->tarea->tarea_id;
            $tipo = 'Proceso/Radicado';
        } elseif ($this->tarea->tarea_type === 'App\Models\Caso') {
            $link = '/casos/' . $this->tarea->tarea_id;
            $tipo = 'Caso';
        } elseif ($this->tarea->tarea_type === 'App\Models\Contrato') { 
            // Usamos la ruta de tu aplicación
            $link = '/gestion/honorarios/contratos/' . $this->tarea->tarea_id;
            $tipo = 'Contrato de Honorarios';
        }
        // ===== FIN DE LA MODIFICACIÓN =====

        return [
            'icon' => 'task', // Un ícono para identificarla en el frontend
            'title' => 'Nueva Tarea Asignada',
            'message' => "Se te asignó la tarea: '{$this->tarea->titulo}'",
            'details' => "Vinculada a: {$tipo}",
            'link' => $link,
            'tarea_id' => $this->tarea->id
        ];
    }
}