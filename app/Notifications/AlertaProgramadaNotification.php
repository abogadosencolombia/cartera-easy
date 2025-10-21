<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class AlertaProgramadaNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $casoId,
        public string $mensaje,
        public bool $esFinal,
        public string $prioridad = 'media',
    ) {
        $this->onQueue('notifications');
    }

    public function via($notifiable): array {
        return [WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        $title = $this->esFinal ? 'Notificación' : 'Recordatorio';
        $url   = route('casos.show', $this->casoId);

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
