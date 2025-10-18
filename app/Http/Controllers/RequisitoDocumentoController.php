<?php

namespace App\Http\Controllers;

use App\Models\RequisitoDocumento;
use App\Models\Cooperativa;
use App\Models\TipoProceso; // <-- IMPORTANTE: Añadir el nuevo modelo
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;

class RequisitoDocumentoController extends Controller
{
    use AuthorizesRequests;

    public function index(): Response
    {
        $this->authorize('isAdmin');

        return Inertia::render('Requisitos/Index', [
            // === CAMBIO REALIZADO ===
            // Ahora cargamos las relaciones con 'cooperativa' y el nuevo 'tipoProceso'
            'requisitos' => RequisitoDocumento::with(['cooperativa', 'tipoProceso'])->get(),
            'cooperativas' => Cooperativa::all(['id', 'nombre']),
            // === CAMBIO REALIZADO ===
            // Obtenemos los tipos de proceso directamente desde la base de datos.
            'tipos_proceso' => TipoProceso::all(['id', 'nombre']),
            // La lista de tipos de documento puede seguir siendo fija por ahora.
            'tipos_documento' => ['pagaré', 'carta instrucciones', 'certificación saldo', 'libranza', 'cédula deudor', 'cédula codeudor', 'otros'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('isAdmin');

        // === CAMBIO REALIZADO ===
        // Ajustamos las reglas de validación para el nuevo campo 'tipo_proceso_id'.
        $validated = $request->validate([
            'cooperativa_id' => 'nullable|exists:cooperativas,id',
            'tipo_proceso_id' => 'required|exists:tipos_proceso,id',
            'tipo_documento_requerido' => 'required|string',
        ]);

        RequisitoDocumento::create($validated);

        return to_route('requisitos.index')->with('success', '¡Requisito creado exitosamente!');
    }

    public function destroy(RequisitoDocumento $requisito): RedirectResponse
    {
        $this->authorize('isAdmin');
        
        $requisito->delete();

        return to_route('requisitos.index')->with('success', '¡Requisito eliminado exitosamente!');
    }
}
