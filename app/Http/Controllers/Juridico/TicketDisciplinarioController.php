<?php

namespace App\Http\Controllers\Juridico;

use App\Models\User; // <-- Añade este 'use' al inicio
use App\Notifications\TicketAsignadoNotification; // <-- Y este también
use App\Http\Controllers\Controller;
use App\Models\IncidenteJuridico;
use App\Models\TicketDisciplinario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TicketDisciplinarioController extends Controller
{
    /**
     * Almacena un nuevo ticket disciplinario para un incidente específico.
     */
    public function store(Request $request, IncidenteJuridico $incidente)
    {
        $request->validate([
            'asignado_a' => 'required|exists:users,id',
            'comentarios' => 'required|string',
        ]);

        // Creamos el ticket
        $ticket = $incidente->tickets()->create([
            'creado_por' => Auth::id(),
            'asignado_a' => $request->asignado_a,
            'comentarios' => $request->comentarios,
            'etapa' => 'nuevo',
        ]);

        // Actualizamos el estado del incidente
        $incidente->update(['estado' => 'en_revision']);

        // ===== ¡AQUÍ ENVIAMOS LA NOTIFICACIÓN! =====
        // 1. Buscamos el modelo del usuario al que se le asignó el ticket.
        $usuarioAsignado = User::find($request->asignado_a);

        // 2. Si el usuario existe, le enviamos la notificación.
        if ($usuarioAsignado) {
            $usuarioAsignado->notify(new TicketAsignadoNotification($ticket));
        }
        // ===========================================

        return back()->with('success', 'Ticket disciplinario creado y asignado.');
    }
}