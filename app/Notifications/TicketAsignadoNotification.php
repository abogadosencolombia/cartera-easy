<?php

namespace App\Notifications;

use App\Models\TicketDisciplinario;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketAsignadoNotification extends Notification
{
    use Queueable;

    protected $ticket;

    /**
     * Create a new notification instance.
     */
    public function __construct(TicketDisciplinario $ticket)
    {
        $this->ticket = $ticket;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        // Enviaremos la notificación a la base de datos (para la campanita) y por correo.
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $incidente = $this->ticket->incidente;
        $url = route('admin.incidentes-juridicos.show', $incidente->id);

        return (new MailMessage)
                    ->subject('Nuevo Ticket Disciplinario Asignado: #' . $this->ticket->id)
                    ->greeting('Hola, ' . $notifiable->name . '!')
                    ->line('Se te ha asignado un nuevo ticket disciplinario para tu revisión.')
                    ->line('Incidente: ' . $incidente->asunto)
                    ->action('Ver Detalles del Incidente', $url)
                    ->line('Gracias por tu diligencia.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        // Esta es la información que se guardará en la base de datos.
        return [
            'ticket_id' => $this->ticket->id,
            'incidente_id' => $this->ticket->incidente->id,
            'asunto_incidente' => $this->ticket->incidente->asunto,
            'mensaje' => 'Se te ha asignado el ticket #' . $this->ticket->id . ' para el incidente: ' . $this->ticket->incidente->asunto,
        ];
    }
}