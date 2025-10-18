<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class IntegracionFallidaNotification extends Notification
{
    use Queueable;

    protected $serviceName;
    protected $errorDetails;

    /**
     * Create a new notification instance.
     *
     * @param string $serviceName El nombre del servicio que falló.
     * @param array $errorDetails Los detalles del último error.
     */
    public function __construct(string $serviceName, array $errorDetails)
    {
        $this->serviceName = $serviceName;
        $this->errorDetails = $errorDetails;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $statusCode = $this->errorDetails['status_code'] ?? 'N/A';
        $body = $this->errorDetails['respuesta_bruta'] ?? 'No disponible';

        return (new MailMessage)
                    ->error() // Esto le da un color rojo y un tono de urgencia al email.
                    ->subject('🚨 Alerta Crítica: Falla en Integración de ' . $this->serviceName)
                    ->greeting('¡Hola, Administrador!')
                    ->line('Se ha detectado una falla crítica y repetida en una de las integraciones externas de la plataforma.')
                    ->line('**Servicio Afectado:** ' . $this->serviceName)
                    ->line('**Detalles del Último Error:**')
                    ->panel( // Un panel resaltado para la información técnica.
                        "Código de Estado: " . $statusCode . "\n" .
                        "Respuesta del Servidor: " . $body
                    )
                    ->action('Ir al Panel de Integraciones', url(route('integraciones.index')))
                    ->line('Se recomienda revisar el estado del servicio externo y los logs de la plataforma para diagnosticar el problema.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'service_name' => $this->serviceName,
            'error_details' => $this->errorDetails,
        ];
    }
}
