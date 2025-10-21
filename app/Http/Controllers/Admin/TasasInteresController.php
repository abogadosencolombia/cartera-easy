<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class TasasInteresController extends Controller
{
    /**
     * Muestra la página para gestionar las tasas de interés.
     * La seguridad se aplica a través del middleware en routes/web.php
     */
    public function index()
    {
        $tasas = DB::table('intereses_tasas')
                    ->orderByDesc('fecha_vigencia')
                    ->get();

        return Inertia::render('Admin/Tasas/Index', [
            'tasas' => $tasas,
        ]);
    }

    /**
     * Guarda una nueva tasa de interés.
     */
    public function store(Request $request)
    {
        // Se elimina la restricción 'after_or_equal:today' para permitir fechas pasadas.
        Validator::make($request->all(), [
            'tasa_ea' => ['required', 'numeric', 'min:0', 'max:100'],
            'fecha_vigencia' => ['required', 'date', 'unique:intereses_tasas,fecha_vigencia'],
        ], [
            'tasa_ea.required' => 'El campo tasa es obligatorio.',
            'tasa_ea.numeric' => 'La tasa debe ser un valor numérico.',
            'fecha_vigencia.required' => 'La fecha de vigencia es obligatoria.',
            'fecha_vigencia.unique' => 'Ya existe una tasa registrada para esta fecha exacta. No se puede duplicar.',
        ])->validate();

        DB::table('intereses_tasas')->insert([
            'tasa_ea' => $request->input('tasa_ea'),
            'fecha_vigencia' => $request->input('fecha_vigencia'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.tasas.index')->with('success', 'Nueva tasa de interés registrada correctamente.');
    }

    /**
     * Elimina una tasa de interés existente.
     */
    public function destroy($tasa_id)
    {
        DB::table('intereses_tasas')->where('id', $tasa_id)->delete();

        return redirect()->route('admin.tasas.index')->with('success', 'Tasa de interés eliminada correctamente.');
    }
}

