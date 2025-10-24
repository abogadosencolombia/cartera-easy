<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast; // <-- 1. Asegúrate de que implementa esto
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatbotResponseReceived implements ShouldBroadcast // <-- 1. (de nuevo)
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $body;
    public $userId;

    /**
     * Create a new event instance.
     */
    public function __construct(string $body, int $userId)
    {
        $this->body = $body;
        $this->userId = $userId;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        // 2. ESTA ES LA PARTE CRUCIAL
        // Le dice a Laravel que envíe este evento al canal privado
        // que coincide exactamente con el que tu frontend está escuchando.
        return [
            new PrivateChannel('App.Models.User.' . $this->userId)
        ];
    }

    /**
     * El nombre del evento que el frontend escuchará.
     * (Opcional pero recomendado)
     */
    public function broadcastAs(): string
    {
        // 3. Este es el nombre que usará tu Echo en el frontend
        return 'chatbot.response';
    }
}