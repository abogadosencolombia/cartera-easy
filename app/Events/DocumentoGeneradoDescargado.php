<?php

namespace App\Events;

use App\Models\DocumentoGenerado;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;

class DocumentoGeneradoDescargado
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public User $user;
    public DocumentoGenerado $documento;
    public Request $request;

    /**
     * Create a new event instance.
     */
    public function __construct(User $user, DocumentoGenerado $documento, Request $request)
    {
        $this->user = $user;
        $this->documento = $documento;
        $this->request = $request;
    }
}
