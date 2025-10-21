<?php

namespace App\Http\Controllers;

use App\Models\ValidacionLegal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ValidacionLegalController extends Controller
{
    /**
     * Actualiza una validación legal, registrando una acción correctiva.
     */
    public function __invoke(Request $request, ValidacionLegal $validacion)
    {
        // Opcional: Autorización para asegurar que solo usuarios permitidos puedan corregir.
        // $this->authorize('corregir', $validacion);

        $request->validate([
            'accion_correctiva' => 'required|string|min:10',
        ]);

        $validacion->update([
            'estado' => 'cumple', // Al corregir, el estado cambia a 'cumple'.
            'accion_correctiva' => $request->accion_correctiva,
            'fecha_cumplimiento' => now(), // Se marca la fecha y hora de la corrección.
        ]);

        // El Observer se encargará de registrar el cambio en el historial y la bitácora.

        return back()->with('success', '¡Falla de cumplimiento corregida exitosamente!');
    }
}