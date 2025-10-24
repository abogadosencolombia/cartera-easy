<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Caso;
use App\Models\ProcesoRadicado;
use App\Models\Contrato;
use App\Models\RevisionDiaria;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class RevisionDiariaController extends Controller
{
    /**
     * Muestra la página principal de revisiones diarias.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $hoy = Carbon::today();

        // --- INICIO: Cargar relaciones ---
        $casos = Caso::with(['deudor', 'cooperativa']) 
            ->orderBy('fecha_vencimiento', 'desc')
            ->paginate(25, ['*'], 'casos_page');

        $radicados = ProcesoRadicado::with(['demandante', 'demandado']) 
            ->orderBy('fecha_proxima_revision', 'desc')
            ->paginate(25, ['*'], 'radicados_page');

        $contratos = Contrato::with(['cliente']) 
            ->where('estado', 'ACTIVO')
            ->orderBy('created_at', 'desc')
            ->paginate(25, ['*'], 'contratos_page');
        // --- FIN: Cargar relaciones ---

        // --- INICIO: CORRECCIÓN - Función para añadir estado y fecha de revisión ---
        $addRevisadoStatus = function ($item) use ($user, $hoy) {
            
            // Buscar la última revisión de este ítem por este usuario
            $latestRevision = RevisionDiaria::where('user_id', $user->id)
                ->where('revisable_id', $item->id)
                ->where('revisable_type', get_class($item))
                ->latest('fecha_revision') // Obtener la más reciente
                ->first();

            if ($latestRevision) {
                // Verificar si la última revisión es de hoy
                $item->revisadoHoy = Carbon::parse($latestRevision->fecha_revision)->isToday();
                // Guardar la fecha de la última revisión (sea de hoy o no)
                $item->ultimaRevisionFecha = Carbon::parse($latestRevision->fecha_revision)->toDateString(); // Formato Y-m-d
            } else {
                $item->revisadoHoy = false;
                $item->ultimaRevisionFecha = null;
            }
            
            return $item;
        };
        // --- FIN: CORRECCIÓN - Función para añadir estado y fecha de revisión ---

        // Aplicar el estado a las colecciones paginadas
        $casos->getCollection()->transform($addRevisadoStatus);
        $radicados->getCollection()->transform($addRevisadoStatus);
        $contratos->getCollection()->transform($addRevisadoStatus);

        return Inertia::render('Revisiones/Index', [
            'casos' => $casos,
            'radicados' => $radicados,
            'contratos' => $contratos,
        ]);
    }

    /**
     * Marca o desmarca un ítem como revisado para el día actual.
     */
    public function toggle(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|integer',
            'tipo' => 'required|string|in:Caso,ProcesoRadicado,Contrato',
        ]);

        $user = Auth::user();
        $hoy = Carbon::today();
        
        // Mapeo de 'tipo' string a la clase del Modelo
        $modelMap = [
            'Caso' => Caso::class,
            'ProcesoRadicado' => ProcesoRadicado::class,
            'Contrato' => Contrato::class,
        ];

        $modelClass = $modelMap[$validated['tipo']] ?? null;

        if (!$modelClass || !class_exists($modelClass)) {
            return back()->with('error', 'Tipo de ítem no válido.');
        }

        // Validar que el ítem exista
        $item = $modelClass::find($validated['id']);
        if (!$item) {
            return back()->with('error', 'El ítem no fue encontrado.');
        }

        // Buscar si ya existe la revisión DE HOY
        $revisionHoy = RevisionDiaria::where('user_id', $user->id)
            ->where('fecha_revision', $hoy)
            ->where('revisable_id', $item->id)
            ->where('revisable_type', $modelClass)
            ->first();

        if ($revisionHoy) {
            // Si existe, la borramos (desmarcar)
            $revisionHoy->delete();
        } else {
            // Si no existe, la creamos (marcar)
            RevisionDiaria::create([
                'user_id' => $user->id,
                'fecha_revision' => $hoy,
                'revisable_id' => $item->id,
                'revisable_type' => $modelClass,
            ]);
        }

        // Redirigir de vuelta a la pestaña activa
        $pageName = strtolower(str_replace('ProcesoRadicado', 'radicado', $validated['tipo'])) . 's_page';
        
        return back(303, [], [
            // Preservar la página actual de la pestaña que se estaba viendo
            'only' => [ strtolower(str_replace('ProcesoRadicado', 'radicado', $validated['tipo'])) . 's' ],
            $pageName => $request->input($pageName, 1)
        ]);
    }
}

