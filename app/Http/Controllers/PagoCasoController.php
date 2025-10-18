<?php

namespace App\Http\Controllers;

use App\Models\Caso;
use App\Models\PagoCaso;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class PagoCasoController extends Controller
{
    use AuthorizesRequests;

    /**
     * Almacena un nuevo pago y su comprobante para un caso específico.
     */
    public function store(Request $request, Caso $caso): RedirectResponse
    {
        $this->authorize('update', $caso);

        $validated = $request->validate([
            'monto_pagado' => ['required', 'numeric', 'min:0'],
            'fecha_pago' => ['required', 'date'],
            'motivo_pago' => ['required', 'in:total,parcial,acuerdo,sentencia'],
            'comprobante_pago' => ['required', 'file', 'image', 'max:5120'], // 5MB Max, solo imágenes
        ]);

        // Guarda el archivo en el disco 'private'
        $path = $request->file('comprobante_pago')->store('comprobantes_pagos', 'private');

        // Crea el registro del pago en la base de datos
        $caso->pagos()->create([
            'user_id' => Auth::id(),
            'monto_pagado' => $validated['monto_pagado'],
            'fecha_pago' => $validated['fecha_pago'],
            'motivo_pago' => $validated['motivo_pago'],
            'comprobante_path' => $path, // Guarda la ruta del archivo
        ]);
        
        // Registra la actividad en la bitácora del caso
        $caso->bitacoras()->create([
            'user_id' => auth()->id(),
            'accion' => 'Registro de Pago',
            'comentario' => 'Se registró un pago de ' . number_format($validated['monto_pagado'], 2) . ' con fecha ' . $validated['fecha_pago']
        ]);

        return back()->with('success', '¡Pago registrado exitosamente!');
    }

    /**
     * Muestra el comprobante de pago almacenado de forma segura.
     */
    public function verComprobante(PagoCaso $pago)
    {
        $this->authorize('view', $pago->caso);

        if (!$pago->comprobante_path || !Storage::disk('private')->exists($pago->comprobante_path)) {
            abort(404, 'Comprobante no encontrado.');
        }

        return Storage::disk('private')->response($pago->comprobante_path);
    }
}
