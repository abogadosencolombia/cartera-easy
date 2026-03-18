<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\ProcesoRadicado;

class ProcesoRevisionNotification extends Notification
{
    // use Queueable;

    protected $proceso;
    protected $tipo; // 'hoy', 'vencida', 'proxima'

    /**
     * Create a new notification instance.
     */
    public function __construct(ProcesoRadicado $proceso, string $tipo = 'hoy')
    {
        $this->proceso = $proceso;
        $this->tipo = $tipo;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        $channels = ['database'];
        
        if (!empty($notifiable->email)) {
            $channels[] = 'mail';
        }
        
        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $titulo = $this->getTitulo();
        $mensaje = $this->getMensaje();

        return (new MailMessage)
                    ->subject('⚖️ ' . $titulo)
                    ->greeting('Hola, ' . $notifiable->name)
                    ->line($mensaje)
                    ->action('Ver Expediente', url('/procesos/' . $this->proceso->id))
                    ->line('Por favor, realice la revisión correspondiente en el sistema.')
                    ->level($this->tipo === 'vencida' ? 'error' : 'warning');
    }

    /**
     * Get the array representation of the notification for the database.
     */
    public function toArray($notifiable): array
    {
        return [
            'proceso_id' => $this->proceso->id,
            'radicado'   => $this->proceso->radicado,
            'titulo'     => $this->getTitulo(),
            'message'    => $this->getMensaje(),
            'link'       => url('/procesos/' . $this->proceso->id),
            'tipo'       => 'revision_proceso',
            'estado'     => $this->tipo,
        ];
    }

    protected function getTitulo(): string
    {
        return match($this->tipo) {
            'vencida' => 'Revisión VENCIDA',
            'hoy'     => 'Revisión para HOY',
            'proxima' => 'Próxima Revisión',
            default   => 'Alerta de Revisión',
        };
    }

    protected function getMensaje(): string
    {
        $fecha = $this->proceso->fecha_proxima_revision ? $this->proceso->fecha_proxima_revision->format('d/m/Y') : 'N/A';
        
        return match($this->tipo) {
            'vencida' => "⚠️ URGENTE: La revisión del proceso {$this->proceso->radicado} está atrasada desde el {$fecha}.",
            'hoy'     => "⚖️ ATENCIÓN: Hoy debes revisar el proceso {$this->proceso->radicado} inmediatamente.",
            'proxima' => "⏳ El proceso {$this->proceso->radicado} requiere revisión el {$fecha}.",
            default   => "El proceso {$this->proceso->radicado} tiene una alerta de revisión.",
        };
    }
}
