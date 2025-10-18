<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ValidacionLegalIncumplida
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * El modelo de la validación que falló.
     */
    public $validacionLegal;

    /**
     * El usuario responsable.
     */
    public $usuario;

    /**
     * Create a new event instance.
     */
    public function __construct($validacionLegal, $usuario)
    {
        $this->validacionLegal = $validacionLegal;
        $this->usuario = $usuario;
    }
}