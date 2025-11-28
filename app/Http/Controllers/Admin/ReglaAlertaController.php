<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReglaAlerta;
use App\Models\Cooperativa;
use App\Models\AuditoriaEvento; // ✅ Importante para el registro de seguridad
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth; // ✅ Necesario para obtener el usuario actual
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

        $regla = ReglaAlerta::create($validated);
        
        // Obtenemos el nombre de la cooperativa para el log
        $nombreCoop = $regla->cooperativa ? $regla->cooperativa->nombre : 'Desconocida';

        // ✅ AUDITORÍA GLOBAL
        AuditoriaEvento::create([
            'user_id' => Auth::id(),
            'evento' => 'CREAR_REGLA_ALERTA',
            'descripcion_breve' => "Nueva regla: {$validated['tipo']} a los {$validated['dias']} días para {$nombreCoop}",
            'criticidad' => 'media',
            'direccion_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return to_route('admin.reglas-alerta.index')->with('success', '¡Regla creada exitosamente!');
    }

    /**
     * Elimina una regla existente.
     * CORREGIDO: Usamos $id en lugar del Modelo inyectado para evitar errores de ruta.
     */
    public function destroy($id): RedirectResponse
    {
        // 1. Buscamos manualmente (Si no existe, fallará aquí y no enviará falso éxito)
        $reglaAlerta = ReglaAlerta::findOrFail($id);

        // Guardamos datos para el log antes de borrar
        $tipo = $reglaAlerta->tipo;
        $dias = $reglaAlerta->dias;
        $coop = $reglaAlerta->cooperativa ? $reglaAlerta->cooperativa->nombre : 'N/A';

        // 2. Borramos el registro real
        $reglaAlerta->delete();

        // ✅ AUDITORÍA GLOBAL
        AuditoriaEvento::create([
            'user_id' => Auth::id(),
            'evento' => 'ELIMINAR_REGLA_ALERTA',
            'descripcion_breve' => "Eliminada regla de {$tipo} ({$dias} días) para {$coop}",
            'criticidad' => 'alta',
            'direccion_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return to_route('admin.reglas-alerta.index')->with('success', '¡Regla eliminada exitosamente!');
    }
}