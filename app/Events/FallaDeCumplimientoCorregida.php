<?php

namespace App\Events;

use App\Models\User;
use App\Models\ValidacionLegal;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;

class FallaDeCumplimientoCorregida
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ValidacionLegal $validacion;
    public User $user;
    public Request $request;

    /**
     * Create a new event instance.
     */
    public function __construct(ValidacionLegal $validacion, User $user, Request $request)
    {
        $this->validacion = $validacion;
        $this->user = $user;
        $this->request = $request;
    }
}
