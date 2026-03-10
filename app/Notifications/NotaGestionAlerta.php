<?php

namespace App\Notifications;

use App\Models\NotaGestion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NotaGestionAlerta extends Notification implements ShouldQueue
{
    use Queueable;

    protected $nota;
    protected $tipo;

    /**
     * Create a new notification instance.
     */
    public function __construct(NotaGestion $nota, string $tipo)
    {
        $this->nota = $nota;
        $this->tipo = $tipo;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $subject = match($this->tipo) {
            'before_expiry' => '⚠️ Alerta de Tiempo: Nota de gestión vence en 1 hora',
            'expired' => '🚫 Tiempo Agotado: Nota de gestión vencida',
            'periodic' => '🔁 Recordatorio: Nota de gestión pendiente (VENCIDA)',
            default => 'Notificación de Gestión Diaria'
        };

        return (new MailMessage)
                    ->subject($subject)
                    ->greeting('Hola ' . $notifiable->name)
                    ->line('Esta es una alerta sobre tu nota de gestión personal:')
                    ->line('**Descripción:** ' . $this->nota->descripcion)
                    ->line('**Despacho:** ' . $this->nota->despacho)
                    ->line('**Término:** ' . $this->nota->termino)
                    ->line('**Fecha Límite:** ' . $this->nota->expires_at->format('d/m/Y h:i A'))
                    ->action('Ir a Gestión Diaria', url('/gestion-diaria'))
                    ->line('Por favor, marca la nota como "Hecha" para detener estas alertas.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'nota_id' => $this->nota->id,
            'mensaje' => match($this->tipo) {
                'before_expiry' => 'Tu nota de gestión vence en menos de 1 hora.',
                'expired' => '¡Atención! Tu nota de gestión ha vencido.',
                'periodic' => 'Recordatorio: Tienes una nota vencida sin completar.',
                default => 'Actualización en tu gestión diaria.'
            },
            'descripcion' => $this->nota->descripcion,
            'url' => '/gestion-diaria'
        ];
    }
}
