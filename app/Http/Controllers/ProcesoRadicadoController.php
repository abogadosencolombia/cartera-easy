<?php

namespace App\Http\Controllers;

use App\Models\ProcesoRadicado;
use App\Http\Requests\StoreProcesoRadicadoRequest;
use App\Http\Requests\UpdateProcesoRadicadoRequest;
use App\Models\User;
use App\Models\AuditoriaEvento;
use App\Models\EtapaProcesal;
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
        $query = ProcesoRadicado::with([
            'abogado', 
            'responsableRevision', 
            'juzgado', 
            'tipoProceso',
            'demandantes', 
            'demandados',
            'etapaActual'
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
                    ->orWhereHas('juzgado', fn($sq) => $sq->where('nombre', 'Ilike', "%{$search}%"))
                    ->orWhereHas('etapaActual', fn($sq) => $sq->where('nombre', 'Ilike', "%{$search}%"));
            });
        }

        if ($request->filled('estado') && in_array($request->input('estado'), ['ACTIVO', 'CERRADO'])) {
            $query->where('estado', $request->input('estado'));
        }

        // ✅ FILTRO INTELIGENTE POR TIPO DE ENTIDAD (Busca en el nombre del juzgado)
        if ($request->filled('tipo_entidad')) {
            $tipo = $request->input('tipo_entidad');
            $query->whereHas('juzgado', function ($q) use ($tipo) {
                // Buscamos si el nombre contiene la palabra clave (Ej: 'Fiscalía')
                $q->where('nombre', 'ilike', "%{$tipo}%");
            });
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
            'filtros'  => $request->only(['search', 'rev_desde', 'rev_hasta', 'estado', 'tipo_entidad']),
            'abogados' => $abogadosParaFiltro,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Radicados/Create', [
            'etapas' => EtapaProcesal::orderBy('orden')->get(['id', 'nombre', 'riesgo'])
        ]);
    }

    public function store(StoreProcesoRadicadoRequest $request)
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;
        $data['estado'] = 'ACTIVO';
        
        if (empty($data['etapa_procesal_id'])) {
            $etapaInicial = EtapaProcesal::orderBy('orden', 'asc')->first();
            if ($etapaInicial) {
                $data['etapa_procesal_id'] = $etapaInicial->id;
            }
        }
        
        $data['fecha_cambio_etapa'] = now();

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

            AuditoriaEvento::create([
                'user_id' => Auth::id(),
                'evento' => 'CREAR_RADICADO',
                'descripcion_breve' => "Creado radicado {$proceso->radicado}",
                'criticidad' => 'media',
                'direccion_ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        });

        return to_route('procesos.show', $proceso)
            ->with('success', 'Radicado creado exitosamente.');
    }

    public function show(ProcesoRadicado $proceso): Response
    {
        $proceso->load([
            'abogado', 'responsableRevision', 'juzgado', 'tipoProceso',
            'demandantes', 'demandados',
            'etapaActual', 
            'documentos' => fn($q) => $q->latest('created_at'),
            'actuaciones' => function ($query) {
                $query->with('user:id,name')->orderBy('fecha_actuacion', 'desc')->orderBy('created_at', 'desc');
            },
            'contrato:id,proceso_id'
        ]);

        return Inertia::render('Radicados/Show', [
            'proceso' => $proceso,
            'etapas' => EtapaProcesal::orderBy('orden')->get(['id', 'nombre', 'riesgo'])
        ]);
    }

    public function edit(ProcesoRadicado $proceso): Response
    {
        $proceso->load([
            'abogado', 'responsableRevision', 'juzgado', 'tipoProceso',
            'demandantes', 'demandados',
            'etapaActual'
        ]);

        return Inertia::render('Radicados/Edit', [
            'proceso' => $proceso,
            'etapas' => EtapaProcesal::orderBy('orden')->get(['id', 'nombre', 'riesgo'])
        ]);
    }

    public function update(UpdateProcesoRadicadoRequest $request, ProcesoRadicado $proceso)
    {
        $data = $request->validated();
        $demandantesIds = $data['demandantes'] ?? [];
        $demandadosIds = $data['demandados'] ?? [];
        unset($data['demandantes'], $data['demandados']);

        DB::transaction(function () use ($proceso, $data, $demandantesIds, $demandadosIds) {
            if (isset($data['etapa_procesal_id']) && $data['etapa_procesal_id'] != $proceso->etapa_procesal_id) {
                $data['fecha_cambio_etapa'] = now();
            }

            $proceso->update($data);
            $proceso->demandantes()->syncWithPivotValues($demandantesIds, ['tipo' => 'DEMANDANTE']);
            $proceso->demandados()->syncWithPivotValues($demandadosIds, ['tipo' => 'DEMANDADO']);

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

    public function updateEtapa(Request $request, ProcesoRadicado $proceso)
    {
        $validated = $request->validate([
            'etapa_procesal_id' => 'required|exists:etapas_procesales,id',
            'observacion' => 'nullable|string|max:500'
        ]);

        $nuevaEtapa = EtapaProcesal::find($validated['etapa_procesal_id']);

        DB::transaction(function () use ($proceso, $nuevaEtapa, $validated) {
            $proceso->update([
                'etapa_procesal_id' => $nuevaEtapa->id,
                'fecha_cambio_etapa' => now(),
            ]);

            $nota = "El proceso avanzó a la etapa: {$nuevaEtapa->nombre}";
            if (!empty($validated['observacion'])) {
                $nota .= ". Observación: {$validated['observacion']}";
            }

            $proceso->actuaciones()->create([
                'nota' => $nota,
                'fecha_actuacion' => now(),
                'user_id' => Auth::id(),
            ]);

            AuditoriaEvento::create([
                'user_id' => Auth::id(),
                'evento' => 'CAMBIO_ETAPA',
                'descripcion_breve' => "Proceso {$proceso->radicado} movido a {$nuevaEtapa->nombre}",
                'criticidad' => 'media',
                'direccion_ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        });

        return back()->with('success', "Etapa actualizada a: {$nuevaEtapa->nombre}");
    }

    public function destroy(ProcesoRadicado $proceso)
    {
        if (Auth::user()->tipo_usuario !== 'admin') {
            AuditoriaEvento::create([
                'user_id' => Auth::id(),
                'evento' => 'INTENTO_ELIMINAR_RADICADO',
                'descripcion_breve' => "Usuario no autorizado intentó eliminar radicado {$proceso->radicado}",
                'criticidad' => 'alta',
                'direccion_ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
            return back()->with('error', 'Acción no autorizada.');
        }

        $radicadoTemp = $proceso->radicado; 
        AuditoriaEvento::create([
            'user_id' => Auth::id(),
            'evento' => 'ELIMINAR_RADICADO',
            'descripcion_breve' => "Eliminado radicado {$radicadoTemp}",
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
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv']]);
        try {
            Excel::import(new ProcesosImport, $request->file('file'));
            AuditoriaEvento::create([
                'user_id' => Auth::id(),
                'evento' => 'IMPORTAR_RADICADOS',
                'descripcion_breve' => "Importación masiva realizada",
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
            return back()->withErrors(['file' => 'Error: '.$e->getMessage()]);
        }
        return to_route('procesos.index')->with('success', 'Importado correctamente.');
    }

    public function close(Request $request, ProcesoRadicado $proceso)
    {
        $request->validate(['nota_cierre' => ['required', 'string', 'max:5000']]);
        $proceso->update(['estado' => 'CERRADO', 'nota_cierre' => $request->input('nota_cierre')]);
        
        AuditoriaEvento::create([
            'user_id' => Auth::id(),
            'evento' => 'CERRAR_RADICADO',
            'descripcion_breve' => "Cierre radicado {$proceso->radicado}",
            'criticidad' => 'media',
            'direccion_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return back()->with('success', 'Caso cerrado.');
    }

    public function reopen(ProcesoRadicado $proceso)
    {
        if (Auth::user()->tipo_usuario !== 'admin') {
             return back()->with('error', 'Solo admins.');
        }

        $proceso->update(['estado' => 'ACTIVO', 'nota_cierre' => null]);
        
        AuditoriaEvento::create([
            'user_id' => Auth::id(),
            'evento' => 'REABRIR_RADICADO',
            'descripcion_breve' => "Reapertura radicado {$proceso->radicado}",
            'criticidad' => 'media',
            'direccion_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return back()->with('success', 'Caso reabierto.');
    }

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

        AuditoriaEvento::create([
            'user_id' => Auth::id(),
            'evento' => 'NUEVA_ACTUACION',
            'descripcion_breve' => "Nueva actuación radicado {$proceso->radicado}",
            'criticidad' => 'baja',
            'direccion_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return back()->with('success', 'Registrado.');
    }

    public function updateActuacion(Request $request, Actuacion $actuacion)
    {
        $user = Auth::user();
        if ($actuacion->actuable_type !== ProcesoRadicado::class || !$user || !in_array($user->tipo_usuario, ['admin', 'gestor', 'abogado'])) {
             abort(403);
        }
        $validated = $request->validate([
            'nota' => ['required', 'string', 'max:5000'],
            'fecha_actuacion' => ['required', 'date', 'before_or_equal:today'],
        ]);
        $actuacion->update($validated);
        if ($actuacion->actuable instanceof ProcesoRadicado) {
            $this->actualizarUltimaActuacion($actuacion->actuable);
        }
        return back(303)->with('success', 'Actualizado.');
    }

    public function destroyActuacion(Actuacion $actuacion)
    {
        $user = Auth::user();
        if ($actuacion->actuable_type !== ProcesoRadicado::class || !$user || !in_array($user->tipo_usuario, ['admin', 'gestor', 'abogado'])) {
             abort(403);
        }
        $proceso = $actuacion->actuable;
        $actuacion->delete();
        if ($proceso instanceof ProcesoRadicado) {
             $this->actualizarUltimaActuacion($proceso);
        }
        return back(303)->with('success', 'Eliminado.');
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

    public function exportarExcel(Request $request)
    {
        $filtros = $request->all();
        $filename = "Reporte_Expedientes_" . Carbon::now()->format('Ymd_His') . ".xlsx";

        AuditoriaEvento::create([
            'user_id' => Auth::id(),
            'evento' => 'EXPORTAR_RADICADOS',
            'descripcion_breve' => "Descarga Excel Expedientes",
            'criticidad' => 'baja',
            'direccion_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return Excel::download(new ProcesosExport($filtros), $filename);
    }
}