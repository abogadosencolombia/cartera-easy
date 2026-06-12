<?php

namespace App\Http\Controllers;

use App\Models\ValidacionLegal;
use App\Services\ExpedienteIntegrityService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class ValidacionLegalController extends Controller
{
    use AuthorizesRequests;

    /**
     * Actualiza una validación legal, registrando una acción correctiva.
     */
    public function __invoke(Request $request, ValidacionLegal $validacion)
    {
        abort_unless($validacion->caso, 404);
        $this->authorize('update', $validacion->caso);

        $request->validate([
            'accion_correctiva' => 'required|string|min:10',
        ]);

        $validacion->update([
            'estado' => 'cumple', // Al corregir, el estado cambia a 'cumple'.
            'accion_correctiva' => $request->accion_correctiva,
            'fecha_cumplimiento' => now(), // Se marca la fecha y hora de la corrección.
        ]);

        // El Observer se encargará de registrar el cambio en el historial y la bitácora.
        if ($validacion->caso) {
            app(ExpedienteIntegrityService::class)->refresh($validacion->caso->refresh());
        }

        return back()->with('success', '¡Falla de cumplimiento corregida exitosamente!');
    }
}
