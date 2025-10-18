<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EventoAuditoriaSospechoso
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * El modelo del evento de auditoría.
     */
    public $eventoAuditoria;

    /**
     * El usuario implicado.
     */
    public $usuario;

    /**
     * Create a new event instance.
     */
    public function __construct($eventoAuditoria, $usuario)
    {
        $this->eventoAuditoria = $eventoAuditoria;
        $this->usuario = $usuario;
    }
}