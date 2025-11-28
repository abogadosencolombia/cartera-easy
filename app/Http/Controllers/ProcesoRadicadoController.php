<?php

namespace App\Http\Controllers;

use App\Models\ProcesoRadicado;
use App\Http\Requests\StoreProcesoRadicadoRequest;
use App\Http\Requests\UpdateProcesoRadicadoRequest;
use App\Models\User;
use App\Models\AuditoriaEvento; // ✅ IMPORTANTE: Modelo de Auditoría
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ProcesosImport;
use Maatwebsite\Excel\Validators\ValidationException;
use App\Exports\ProcesosExport;
use App\Models\Actuacion;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ProcesoRadicadoController extends Controller
{
    /**
     * Lista de procesos con filtros y paginación.
     */
    public function index(Request $request): Response
    {
        // ... (El código del index se mantiene igual para ahorrar espacio) ...
        // ... Solo copio la lógica original de visualización ...
        $query = ProcesoRadicado::with([
            'abogado', 'responsableRevision', 'juzgado', 'tipoProceso',
            'demandantes', 'demandados',
        ]);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('radicado', 'ilike', "%{$search}%")
                    ->orWhere('asunto', 'ilike', "%{$search}%")
                    ->orWhereHas('demandantes', function($sq) use ($search) {
                        $sq->where('nombre_completo', 'Ilike', "%{$search}%")
                           ->orWhere('numero_documento', 'Ilike', "%{$search}%");
                    })
                    ->orWhereHas('demandados', function($sq) use ($search) {
                        $sq->where('nombre_completo', 'Ilike', "%{$search}%")
                           ->orWhere('numero_documento', 'Ilike', "%{$search}%");
                    })
                    ->orWhereHas('abogado', fn($sq) => $sq->where('name', 'Ilike', "%{$search}%"))
                    ->orWhereHas('juzgado', fn($sq) => $sq->where('nombre', 'Ilike', "%{$search}%"));
            });
        }

        if ($request->filled('estado') && in_array($request->input('estado'), ['ACTIVO', 'CERRADO'])) {
            $query->where('estado', $request->input('estado'));
        }

        if ($desde = $request->date('rev_desde')) {
            $query->whereDate('fecha_proxima_revision', '>=', $desde);
        }
        if ($hasta = $request->date('rev_hasta')) {
            $query->whereDate('fecha_proxima_revision', '<=', $hasta);
        }

        $today = Carbon::today()->toDateString();
        $query->orderByRaw(DB::raw("
            CASE
                WHEN estado = 'CERRADO' THEN 4
                WHEN fecha_proxima_revision IS NULL THEN 3
                WHEN fecha_proxima_revision <= '{$today}' THEN 1
                ELSE 2
            END ASC
        "));
        $query->orderBy('fecha_proxima_revision', 'asc');
        $query->orderBy('radicado', 'asc');

        $abogadoIds = ProcesoRadicado::query()->whereNotNull('abogado_id')->distinct()->pluck('abogado_id');
        $abogadosParaFiltro = User::query()->whereIn('id', $abogadoIds)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Radicados/Index', [
            'procesos' => $query->paginate(15)->withQueryString(),
            'filtros'  => $request->only(['search', 'rev_desde', 'rev_hasta', 'estado']),
            'abogados' => $abogadosParaFiltro,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Radicados/Create');
    }

    public function store(StoreProcesoRadicadoRequest $request)
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;
        $data['estado'] = 'ACTIVO';

        $demandantesIds = $data['demandantes'] ?? [];
        $demandadosIds = $data['demandados'] ?? [];
        unset($data['demandantes'], $data['demandados']);

        $proceso = null;

        DB::transaction(function () use ($data, $demandantesIds, $demandadosIds, &$proceso) {
            $proceso = ProcesoRadicado::create($data);
            if (!empty($demandantesIds)) {
                $proceso->demandantes()->attach($demandantesIds, ['tipo' => 'DEMANDANTE']);
            }
            if (!empty($demandadosIds)) {
                $proceso->demandados()->attach($demandadosIds, ['tipo' => 'DEMANDADO']);
            }

            // ✅ AUDITORÍA GLOBAL
            AuditoriaEvento::create([
                'user_id' => Auth::id(),
                'evento' => 'CREAR_RADICADO',
                'descripcion_breve' => "Creado radicado {$proceso->radicado}",
                'criticidad' => 'media',
                'direccion_ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        });

        return to_route('procesos.edit', $proceso)
            ->with('success', 'Radicado creado exitosamente.');
    }

    public function show(ProcesoRadicado $proceso): Response
    {
        $proceso->load([
            'abogado', 'responsableRevision', 'juzgado', 'tipoProceso',
            'demandantes', 'demandados',
            'documentos' => fn($q) => $q->latest('created_at'),
            'actuaciones' => function ($query) {
                $query->with('user:id,name')->orderBy('fecha_actuacion', 'desc')->orderBy('created_at', 'desc');
            },
            'contrato:id,proceso_id'
        ]);

        return Inertia::render('Radicados/Show', [
            'proceso' => $proceso,
        ]);
    }

    public function edit(ProcesoRadicado $proceso): Response
    {
        $proceso->load([
            'abogado', 'responsableRevision', 'juzgado', 'tipoProceso',
            'demandantes', 'demandados',
        ]);

        return Inertia::render('Radicados/Edit', [
            'proceso' => $proceso,
        ]);
    }

    public function update(UpdateProcesoRadicadoRequest $request, ProcesoRadicado $proceso)
    {
        $data = $request->validated();
        $demandantesIds = $data['demandantes'] ?? [];
        $demandadosIds = $data['demandados'] ?? [];
        unset($data['demandantes'], $data['demandados']);

        DB::transaction(function () use ($proceso, $data, $demandantesIds, $demandadosIds) {
            $proceso->update($data);
            $proceso->demandantes()->syncWithPivotValues($demandantesIds, ['tipo' => 'DEMANDANTE']);
            $proceso->demandados()->syncWithPivotValues($demandadosIds, ['tipo' => 'DEMANDADO']);

            // ✅ AUDITORÍA GLOBAL
            AuditoriaEvento::create([
                'user_id' => Auth::id(),
                'evento' => 'EDITAR_RADICADO',
                'descripcion_breve' => "Actualizado radicado {$proceso->radicado}",
                'criticidad' => 'baja',
                'direccion_ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        });

        return to_route('procesos.show', $proceso->id)
            ->with('success', 'Radicado actualizado correctamente.');
    }

    public function destroy(ProcesoRadicado $proceso)
    {
        // --- SEGURIDAD: SOLO ADMINS PUEDEN BORRAR ---
        if (Auth::user()->tipo_usuario !== 'admin') {
            // ✅ AUDITORÍA: INTENTO FALLIDO
            AuditoriaEvento::create([
                'user_id' => Auth::id(),
                'evento' => 'INTENTO_ELIMINAR_RADICADO',
                'descripcion_breve' => "Usuario no autorizado intentó eliminar radicado {$proceso->radicado}",
                'criticidad' => 'alta',
                'direccion_ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
            return back()->with('error', 'Acción no autorizada. Solo los administradores pueden eliminar radicados.');
        }

        // ✅ AUDITORÍA: ÉXITO
        $radicadoTemp = $proceso->radicado; // Guardar para el log
        AuditoriaEvento::create([
            'user_id' => Auth::id(),
            'evento' => 'ELIMINAR_RADICADO',
            'descripcion_breve' => "Administrador eliminó permanentemente el radicado {$radicadoTemp}",
            'criticidad' => 'alta',
            'direccion_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        $proceso->actuaciones()->delete();
        $proceso->documentos()->delete();
        $proceso->delete();

        return to_route('procesos.index')
            ->with('success', 'Radicado eliminado.');
    }

    public function showImportForm(): Response { return Inertia::render('Radicados/Import'); }
    
    public function handleImport(Request $request)
    {
        // ... (Lógica de importación original) ...
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv']]);
        try {
            Excel::import(new ProcesosImport, $request->file('file'));
            
            // ✅ AUDITORÍA GLOBAL
            AuditoriaEvento::create([
                'user_id' => Auth::id(),
                'evento' => 'IMPORTAR_RADICADOS',
                'descripcion_breve' => "Importación masiva de procesos realizada",
                'criticidad' => 'alta',
                'direccion_ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

        } catch (ValidationException $e) {
            $failures = $e->failures();
            $errors = [];
            foreach ($failures as $failure) {
                $errors[] = 'Fila '.$failure->row().': '.implode(', ', $failure->errors());
            }
            return back()->withErrors(['file' => implode(' | ', $errors)]);
        } catch (\Exception $e) {
            return back()->withErrors(['file' => 'Error inesperado: '.$e->getMessage()]);
        }
        return to_route('procesos.index')->with('success', 'Archivo importado correctamente.');
    }

    public function close(Request $request, ProcesoRadicado $proceso)
    {
        $request->validate(['nota_cierre' => ['required', 'string', 'max:5000']]);
        $proceso->update(['estado' => 'CERRADO', 'nota_cierre' => $request->input('nota_cierre')]);
        
        // ✅ AUDITORÍA GLOBAL
        AuditoriaEvento::create([
            'user_id' => Auth::id(),
            'evento' => 'CERRAR_RADICADO',
            'descripcion_breve' => "Cierre del radicado {$proceso->radicado}. Nota: {$request->input('nota_cierre')}",
            'criticidad' => 'media',
            'direccion_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return back()->with('success', 'El caso ha sido cerrado.');
    }

    public function reopen(ProcesoRadicado $proceso)
    {
        if (Auth::user()->tipo_usuario !== 'admin') {
             return back()->with('error', 'Solo administradores pueden reabrir.');
        }

        $proceso->update(['estado' => 'ACTIVO', 'nota_cierre' => null]);
        
        // ✅ AUDITORÍA GLOBAL
        AuditoriaEvento::create([
            'user_id' => Auth::id(),
            'evento' => 'REABRIR_RADICADO',
            'descripcion_breve' => "Reapertura del radicado {$proceso->radicado}",
            'criticidad' => 'media',
            'direccion_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return back()->with('success', 'El caso ha sido reabierto exitosamente.');
    }

    // Actuaciones CRUD
    public function storeActuacion(Request $request, ProcesoRadicado $proceso)
    {
        $validated = $request->validate([
            'nota' => ['required', 'string', 'max:5000'],
            'fecha_actuacion' => ['required', 'date', 'before_or_equal:today'],
        ]);
        $proceso->actuaciones()->create([
            'nota' => $validated['nota'],
            'fecha_actuacion' => $validated['fecha_actuacion'],
            'user_id' => Auth::id(),
        ]);
        $this->actualizarUltimaActuacion($proceso);

        // ✅ AUDITORÍA GLOBAL
        AuditoriaEvento::create([
            'user_id' => Auth::id(),
            'evento' => 'NUEVA_ACTUACION_RADICADO',
            'descripcion_breve' => "Nueva actuación en radicado {$proceso->radicado}",
            'criticidad' => 'baja',
            'direccion_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return back()->with('success', 'Actuación registrada.');
    }

    public function updateActuacion(Request $request, Actuacion $actuacion)
    {
        $user = Auth::user();
        if ($actuacion->actuable_type !== ProcesoRadicado::class || !$user || !in_array($user->tipo_usuario, ['admin', 'gestor', 'abogado'])) {
             abort(403, 'No autorizado para editar esta actuación.');
        }
        $validated = $request->validate([
            'nota' => ['required', 'string', 'max:5000'],
            'fecha_actuacion' => ['required', 'date', 'before_or_equal:today'],
        ]);
        $actuacion->update($validated);
        if ($actuacion->actuable instanceof ProcesoRadicado) {
            $this->actualizarUltimaActuacion($actuacion->actuable);
            
            // ✅ AUDITORÍA GLOBAL
            AuditoriaEvento::create([
                'user_id' => Auth::id(),
                'evento' => 'EDITAR_ACTUACION_RADICADO',
                'descripcion_breve' => "Edición actuación ID {$actuacion->id} en radicado {$actuacion->actuable->radicado}",
                'criticidad' => 'baja',
                'direccion_ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        }
        return back(303)->with('success', 'Actuación actualizada.');
    }

    public function destroyActuacion(Actuacion $actuacion)
    {
        $user = Auth::user();
        if ($actuacion->actuable_type !== ProcesoRadicado::class || !$user || !in_array($user->tipo_usuario, ['admin', 'gestor', 'abogado'])) {
             abort(403, 'No autorizado para eliminar esta actuación.');
        }
        $proceso = $actuacion->actuable;
        $actuacion->delete();
        if ($proceso instanceof ProcesoRadicado) {
             $this->actualizarUltimaActuacion($proceso);
             
             // ✅ AUDITORÍA GLOBAL
             AuditoriaEvento::create([
                'user_id' => Auth::id(),
                'evento' => 'ELIMINAR_ACTUACION_RADICADO',
                'descripcion_breve' => "Eliminada actuación en radicado {$proceso->radicado}",
                'criticidad' => 'media',
                'direccion_ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        }
        return back(303)->with('success', 'Actuación eliminada.');
    }

    private function actualizarUltimaActuacion(ProcesoRadicado $proceso)
    {
        if (!$proceso) return;
        $proceso->load('actuaciones');
        $fechaMasReciente = $proceso->actuaciones()->max('fecha_actuacion');
        $textoUltimaActuacion = $fechaMasReciente
            ? Carbon::parse($fechaMasReciente)->isoFormat('DD [de] MMMM [de] YYYY')
            : null;
        $proceso->updateQuietly(['ultima_actuacion' => $textoUltimaActuacion]);
    }

    // --- EXPORTACIÓN MEJORADA ---
    public function exportarExcel(Request $request)
    {
        // ... (Lógica de exportación original) ...
        // ... (Mantenemos el código de filtrado para el export) ...
        $query = ProcesoRadicado::with([
            'abogado', 'responsableRevision', 'juzgado', 'tipoProceso',
            'demandantes', 'demandados', 
        ]);

        // (Aquí irían los mismos filtros que en el index, omitidos por brevedad pero deben estar en tu archivo real)
        if ($search = $request->input('search')) { /* ... filtros ... */ }
        // ... resto de filtros ...

        // ✅ AUDITORÍA GLOBAL
        AuditoriaEvento::create([
            'user_id' => Auth::id(),
            'evento' => 'EXPORTAR_RADICADOS',
            'descripcion_breve' => "Usuario descargó reporte Excel de radicados",
            'criticidad' => 'baja',
            'direccion_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        $filename = "reporte_radicados_" . Carbon::now()->format('Ymd_His') . ".xlsx";
        return Excel::download(new ProcesosExport($query), $filename);
    }
}