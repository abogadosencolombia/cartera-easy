<?php

namespace App\Listeners;

use App\Events\UserLoggedIn;
use App\Models\AuditoriaEvento;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class LogSuccessfulLogin
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(UserLoggedIn $event): void
    {
        AuditoriaEvento::create([
            'user_id' => $event->user->id,
            'evento' => 'LOGIN_EXITOSO',
            'descripcion_breve' => "El usuario '{$event->user->name}' ha iniciado sesión.",
            'criticidad' => 'baja',
            'direccion_ip' => $event->request->ip(),
            'user_agent' => $event->request->userAgent(),
        ]);
    }
}
