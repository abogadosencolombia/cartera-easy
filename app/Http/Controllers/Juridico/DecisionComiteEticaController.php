<?php

namespace App\Http\Controllers\Juridico;

use App\Http\Controllers\Controller;
use App\Models\TicketDisciplinario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DecisionComiteEticaController extends Controller
{
    /**
     * Almacena la decisión final para un ticket disciplinario.
     */
    public function store(Request $request, TicketDisciplinario $ticket)
    {
        $request->validate([
            'resultado' => 'required|in:sin_falta,falta_leve,falta_grave,sancionado',
            'resumen_decision' => 'required|string',
            'medida_administrativa' => 'nullable|string',
            'requiere_capacitacion' => 'required|boolean',
        ]);

        // Creamos la decisión y la asociamos al ticket
        $ticket->decision()->create([
            'revisado_por' => Auth::id(),
            'resumen_decision' => $request->resumen_decision,
            'resultado' => $request->resultado,
            'fecha_revision' => now(), // Usamos la fecha actual
            'medida_administrativa' => $request->medida_administrativa,
            'requiere_capacitacion' => $request->requiere_capacitacion,
        ]);

        // Actualizamos la etapa del ticket a 'cerrado'
        $ticket->update(['etapa' => 'cerrado']);

        // Opcional: Podríamos añadir lógica para verificar si todos los tickets
        // de un incidente están cerrados para cambiar el estado del incidente a 'resuelto'.
        // Por ahora, lo dejamos así para mantenerlo simple.

        return back()->with('success', 'Decisión registrada y ticket cerrado con éxito.');
    }
}