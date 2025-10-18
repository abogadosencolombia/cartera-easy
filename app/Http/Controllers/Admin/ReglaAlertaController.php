<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReglaAlerta;
use App\Models\Cooperativa;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Validation\Rule;

class ReglaAlertaController extends Controller
{
    /**
     * Muestra el panel de gestión de reglas de alerta.
     */
    public function index(): Response
    {
        return Inertia::render('Admin/ReglasAlerta/Index', [
            'reglas' => ReglaAlerta::with('cooperativa')->orderBy('cooperativa_id')->get(),
            'cooperativas' => Cooperativa::all(['id', 'nombre']),
            'tipos_proceso' => ['ejecutivo singular', 'hipotecario', 'prendario', 'libranza'],
            'tipos_alerta' => ['mora', 'vencimiento', 'inactividad', 'documento_faltante'],
        ]);
    }

    /**
     * Guarda una nueva regla en la base de datos.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'cooperativa_id' => 'required|exists:cooperativas,id',
            'tipo' => ['required', Rule::in(['mora', 'vencimiento', 'inactividad', 'documento_faltante'])],
            'dias' => 'required|integer|min:1',
        ]);

        // Prevenir duplicados
        $existente = ReglaAlerta::where('cooperativa_id', $validated['cooperativa_id'])
            ->where('tipo', $validated['tipo'])
            ->exists();

        if ($existente) {
            return back()->with('error', 'Ya existe una regla de este tipo para la cooperativa seleccionada.');
        }

        ReglaAlerta::create($validated);

        return to_route('admin.reglas-alerta.index')->with('success', '¡Regla creada exitosamente!');
    }

    /**
     * Elimina una regla existente.
     */
    public function destroy(ReglaAlerta $reglaAlerta): RedirectResponse
    {
        $reglaAlerta->delete();

        return to_route('admin.reglas-alerta.index')->with('success', '¡Regla eliminada exitosamente!');
    }
}
