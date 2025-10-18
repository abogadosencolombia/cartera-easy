<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class DebugPushNotification extends Notification
{
    use Queueable;

    public function via($notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        $url = route('dashboard');

        return (new WebPushMessage)
            ->title('✅ Push de prueba')
            ->icon(asset('icons/icon-192.png'))
            ->body('Si ves esto, Web Push está funcionando.')
            ->action('Abrir', $url)
            ->data(['url' => $url]);
    }
}
