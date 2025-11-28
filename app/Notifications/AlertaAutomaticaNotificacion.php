<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AlertaAutomaticaNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $datos;

    /**
     * Create a new notification instance.
     * $datos espera: ['titulo', 'mensaje', 'url', 'tipo']
     */
    public function __construct($datos)
    {
        $this->datos = $datos;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        // Enviar por correo y guardar en base de datos (campanita)
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $color = match($this->datos['tipo']) {
            'mora' => 'error', // Rojo
            'vencimiento' => 'warning', // Amarillo
            default => 'primary', // Azul
        };

        return (new MailMessage)
                    ->subject('⚠️ Alerta del Sistema: ' . $this->datos['titulo'])
                    ->greeting('Hola, ' . $notifiable->name)
                    ->line($this->datos['mensaje'])
                    ->action('Ver Detalles', $this->datos['url'])
                    ->line('Esta es una notificación automática basada en sus reglas configuradas.')
                    ->level($color);
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [
            'titulo'  => $this->datos['titulo'],
            'mensaje' => $this->datos['mensaje'],
            'url'     => $this->datos['url'],
            'tipo'    => $this->datos['tipo'],
        ];
    }
}