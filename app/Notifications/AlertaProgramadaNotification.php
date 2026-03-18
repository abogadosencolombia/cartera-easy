<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class AlertaProgramadaNotification extends Notification
{
    use Queueable;

    public function __construct(
        public ?int $casoId,
        public string $mensaje,
        public bool $esFinal,
        public string $prioridad = 'media',
        public ?int $procesoId = null,
    ) {
    }

    public function via($notifiable): array {
        $channels = [WebPushChannel::class];
        
        if (!empty($notifiable->email)) {
            $channels[] = 'mail';
        }
        
        return $channels;
    }

    public function toMail($notifiable): \App\Mail\AlertaSistemaMailable
    {
        $title = $this->esFinal ? 'Notificación Programada' : 'Recordatorio de Tarea';
        
        // Determinar URL y contexto de forma segura
        if ($this->procesoId) {
            $url = route('procesos.show', $this->procesoId);
            $context = 'Esta es una alerta programada vinculada al expediente #' . $this->procesoId;
        } elseif ($this->casoId) {
            $url = route('casos.show', $this->casoId);
            $context = 'Esta es una alerta programada vinculada al caso #' . $this->casoId;
        } else {
            $url = route('dashboard');
            $context = 'Esta es una alerta general del sistema.';
        }
        
        return (new \App\Mail\AlertaSistemaMailable(
            $notifiable->name,
            $title,
            $this->mensaje,
            $url,
            $context
        ))->to($notifiable->email);
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        $title = $this->esFinal ? 'Notificación' : 'Recordatorio';
        
        if ($this->procesoId) {
            $url = route('procesos.show', $this->procesoId);
        } elseif ($this->casoId) {
            $url = route('casos.show', $this->casoId);
        } else {
            $url = route('dashboard');
        }

        return (new WebPushMessage)
            ->title($title)
            ->icon(asset('icons/icon-192.png'))
            ->body($this->mensaje)
            ->data(['url' => $url, 'prioridad' => $this->prioridad]);
    }

    public function viaQueues(): array {
        return [ WebPushChannel::class => 'notifications' ];
    }
}
