<?php

namespace App\Http\Controllers;

use App\Models\Persona;
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
use App\Traits\RegistraRevisionTrait;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

use App\Notifications\ProcesoRevisionNotification;
use Illuminate\Support\Facades\Notification;

class ProcesoRadicadoController extends Controller
{
    use RegistraRevisionTrait, AuthorizesRequests;

    /**
     * Helper para notificar revisión si es hoy o pasado.
     */
    private function notificarRevisionInmediata(ProcesoRadicado $proceso)
    {
        if (!$proceso->fecha_proxima_revision) return;

        $fecha = Carbon::parse($proceso->fecha_proxima_revision);
        $hoy = Carbon::today();
        $tipoAlerta = null;

        if ($fecha->isSameDay($hoy)) {
            $tipoAlerta = 'hoy';
        } elseif ($fecha->isPast()) {
            $tipoAlerta = 'vencida';
        }

        if ($tipoAlerta) {
            $destinatarios = collect();
            if ($proceso->abogado_id) $destinatarios->push(User::find($proceso->abogado_id));
            if ($proceso->responsable_revision_id) $destinatarios->push(User::find($proceso->responsable_revision_id));
            
            $destinatarios = $destinatarios->filter()->unique('id');

            if ($destinatarios->isNotEmpty()) {
                Notification::send($destinatarios, new ProcesoRevisionNotification($proceso, $tipoAlerta));
            }
        }
    }

    /**
     * Lista de procesos con filtros y paginación.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ProcesoRadicado::class);
        
        $user = Auth::user();
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
                    ->orWhere('naturaleza', 'ilike', "%{$search}%")
                    ->orWhere('ultima_actuacion', 'ilike', "%{$search}%")
                    ->orWhere('observaciones', 'ilike', "%{$search}%")
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
                    ->orWhereHas('etapaActual', fn($sq) => $sq->where('nombre', 'Ilike', "%{$search}%"))
                    ->orWhereHas('tipoProceso', fn($sq) => $sq->where('nombre', 'Ilike', "%{$search}%"));
            });
        }

        if ($request->filled('estado') && in_array($request->input('estado'), ['ACTIVO', 'CERRADO'])) {
            $query->where('estado', $request->input('estado'));
        }

        if ($request->filled('tipo_entidad')) {
            $tipo = $request->input('tipo_entidad');
            $query->whereHas('juzgado', function ($q) use ($tipo) {
                $q->where('nombre', 'ilike', "%{$tipo}%");
            });
        }

        if ($desde = $request->date('rev_desde')) {
            $query->whereDate('fecha_proxima_revision', '>=', $desde);
        }
        if ($hasta = $request->date('rev_hasta')) {
            $query->whereDate('fecha_proxima_revision', '<=', $hasta);
        }

        $procesos = $query->latest('updated_at')->paginate(15)->withQueryString();

        return Inertia::render('Radicados/Index', [
            'procesos' => $procesos,
            'filtros'  => $request->all(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', ProcesoRadicado::class);
        return Inertia::render('Radicados/Create', [
            'etapas' => EtapaProcesal::orderBy('orden')->get(['id', 'nombre'])
        ]);
    }

    public function store(StoreProcesoRadicadoRequest $request)
    {
        $this->authorize('create', ProcesoRadicado::class);
        $data = $request->validated();
        
        if ($user = Auth::user()) {
            $data['created_by'] = $user->id;
            if (empty($data['abogado_id'])) {
                $data['abogado_id'] = $user->id;
            }
        }
        
        $data['fecha_cambio_etapa'] = now();

        $demandantesRaw = $request->input('demandantes', []);
        $demandadosRaw = $request->input('demandados', []);
        unset($data['demandantes'], $data['demandados']);

        $proceso = null;

        try {
            DB::transaction(function () use ($data, $demandantesRaw, $demandadosRaw, &$proceso) {
                $resDte = $this->procesarPartes($demandantesRaw, 'DEMANDANTE');
                $resDdo = $this->procesarPartes($demandadosRaw, 'DEMANDADO');

                $data['info_incompleta'] = $resDte['info_incompleta'] || $resDdo['info_incompleta'];

                $proceso = ProcesoRadicado::create($data);

                if (!empty($resDte['ids'])) {
                    $proceso->demandantes()->attach($resDte['ids'], ['tipo' => 'DEMANDANTE']);
                }
                if (!empty($resDdo['ids'])) {
                    $proceso->demandados()->attach($resDdo['ids'], ['tipo' => 'DEMANDADO']);
                }

                AuditoriaEvento::create([
                    'user_id' => Auth::id(),
                    'evento' => 'CREAR_RADICADO',
                    'descripcion_breve' => "Creado radicado {$proceso->radicado}",
                    'auditable_id' => $proceso->id,
                    'auditable_type' => ProcesoRadicado::class,
                    'detalle_nuevo' => $proceso->getRawOriginal(),
                    'criticidad' => 'media',
                    'direccion_ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            });

            // Notificación inmediata si aplica
            $this->notificarRevisionInmediata($proceso);
        } catch (\Exception $e) {
            \Log::error("Error al crear radicado: " . $e->getMessage(), [
                'exception' => $e,
                'data' => $request->all()
            ]);
            return back()->withInput()->with('error', 'No se pudo crear el radicado: ' . $e->getMessage());
        }

        return to_route('procesos.show', $proceso)
            ->with('success', 'Radicado creado exitosamente.');
    }

    public function show(ProcesoRadicado $proceso): Response
    {
        $this->authorize('view', $proceso);
        
        $this->registrarRevisionAutomatica($proceso);

        $proceso->load([
            'abogado', 'responsableRevision', 'juzgado', 'tipoProceso',
            'demandantes.cooperativas', 'demandantes.abogados',
            'demandados.cooperativas', 'demandados.abogados',
            'documentos.uploader',
            'actuaciones.user',
            'etapaActual'
        ]);

        return Inertia::render('Radicados/Show', [
            'proceso' => $proceso,
            'etapas'  => EtapaProcesal::orderBy('orden')->get(['id', 'nombre', 'riesgo'])
        ]);
    }

    public function edit(ProcesoRadicado $proceso): Response
    {
        $this->authorize('update', $proceso);
        $proceso->load([
            'abogado', 'responsableRevision', 'juzgado', 'tipoProceso',
            'demandantes.cooperativas', 'demandantes.abogados',
            'demandados.cooperativas', 'demandados.abogados',
            'etapaActual'
        ]);

        return Inertia::render('Radicados/Edit', [
            'proceso' => $proceso,
            'etapas' => EtapaProcesal::orderBy('orden')->get(['id', 'nombre', 'riesgo'])
        ]);
    }

    public function update(UpdateProcesoRadicadoRequest $request, ProcesoRadicado $proceso)
    {
        $this->authorize('update', $proceso);
        $data = $request->validated();
        $demandantesRaw = $request->input('demandantes', []);
        $demandadosRaw = $request->input('demandados', []);
        unset($data['demandantes'], $data['demandados']);

        try {
            DB::transaction(function () use ($proceso, $data, $demandantesRaw, $demandadosRaw) {
                if (isset($data['etapa_procesal_id']) && $data['etapa_procesal_id'] != $proceso->etapa_procesal_id) {
                    $data['fecha_cambio_etapa'] = now();
                }

                $resDte = $this->procesarPartes($demandantesRaw, 'DEMANDANTE');
                $resDdo = $this->procesarPartes($demandadosRaw, 'DEMANDADO');

                $data['info_incompleta'] = $resDte['info_incompleta'] || $resDdo['info_incompleta'];

                $original = $proceso->getRawOriginal();
                $proceso->update($data);
                $changes = $proceso->getChanges();

                if (!empty($changes)) {
                    $anterior = [];
                    $nuevo = [];
                    foreach ($changes as $key => $val) {
                        if (in_array($key, ['updated_at'])) continue;
                        $anterior[$key] = $original[$key] ?? null;
                        $nuevo[$key] = $val;
                    }

                    if (!empty($nuevo)) {
                        AuditoriaEvento::create([
                            'user_id' => Auth::id(),
                            'evento' => 'EDITAR_RADICADO',
                            'descripcion_breve' => "Actualización de datos del radicado {$proceso->radicado}",
                            'auditable_id' => $proceso->id,
                            'auditable_type' => ProcesoRadicado::class,
                            'criticidad' => 'baja',
                            'detalle_anterior' => $anterior,
                            'detalle_nuevo' => $nuevo,
                            'direccion_ip' => request()->ip(),
                            'user_agent' => request()->userAgent(),
                        ]);
                    }
                }

                $proceso->demandantes()->syncWithPivotValues($resDte['ids'], ['tipo' => 'DEMANDANTE']);
                $proceso->demandados()->syncWithPivotValues($resDdo['ids'], ['tipo' => 'DEMANDADO']);
            });

            // Notificación inmediata si aplica
            $this->notificarRevisionInmediata($proceso);
        } catch (\Exception $e) {
            \Log::error("Error al actualizar radicado: " . $e->getMessage(), [
                'exception' => $e,
                'proceso_id' => $proceso->id,
                'data' => $request->all()
            ]);
            return back()->withInput()->with('error', 'No se pudo actualizar el radicado: ' . $e->getMessage());
        }

        return to_route('procesos.show', $proceso->id)
            ->with('success', 'Radicado actualizado correctamente.');
    }

    public function updateEtapa(Request $request, ProcesoRadicado $proceso)
    {
        $this->authorize('update', $proceso);
        $validated = $request->validate([
            'etapa_procesal_id' => 'required|exists:etapas_procesales,id',
            'observacion' => 'nullable|string|max:500',
            'fecha_proxima_revision' => 'required|date|after_or_equal:today',
        ]);

        $nuevaEtapa = EtapaProcesal::find($validated['etapa_procesal_id']);

        DB::transaction(function () use ($proceso, $nuevaEtapa, $validated) {
            $proceso->update([
                'etapa_procesal_id' => $nuevaEtapa->id,
                'fecha_cambio_etapa' => now(),
                'fecha_proxima_revision' => $validated['fecha_proxima_revision'],
            ]);

            $nota = "El proceso avanzó a la etapa: {$nuevaEtapa->nombre}";
            if (!empty($validated['observacion'])) {
                $nota .= ". Observación: {$validated['observacion']}";
            }

            $proceso->actuaciones()->create([
                'user_id' => Auth::id(),
                'nota'    => $nota,
                'fecha_actuacion' => now()
            ]);

            AuditoriaEvento::create([
                'user_id' => Auth::id(),
                'evento' => 'CAMBIO_ETAPA_RADICADO',
                'descripcion_breve' => "Radicado {$proceso->radicado} movido a etapa {$nuevaEtapa->nombre} (Próxima revisión: {$validated['fecha_proxima_revision']})",
                'auditable_id' => $proceso->id,
                'auditable_type' => ProcesoRadicado::class,
                'criticidad' => 'media',
                'direccion_ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        });

        return back()->with('success', "Etapa actualizada a {$nuevaEtapa->nombre} y próxima revisión agendada.");
    }

    public function destroy(ProcesoRadicado $proceso)
    {
        $this->authorize('delete', $proceso);
        $radicado = $proceso->radicado;
        $proceso->delete();

        AuditoriaEvento::create([
            'user_id' => Auth::id(),
            'evento' => 'ELIMINAR_RADICADO',
            'descripcion_breve' => "Eliminado radicado {$radicado}",
            'criticidad' => 'alta',
            'direccion_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return to_route('procesos.index')->with('success', 'Radicado eliminado.');
    }

    private function procesarPartes(array $partes, string $tipo): array
    {
        $ids = [];
        $infoIncompleta = false;

        foreach ($partes as $parte) {
            $id = $parte['id'] ?? null;
            
            // CRÍTICO: Si el frontend solo envía el ID (es decir, no es una persona "nueva" o en edición "incompleta"),
            // no debemos sobrescribir sus datos, solo vincularla.
            if (!empty($id) && empty($parte['is_new']) && empty($parte['nombre_completo']) && empty($parte['numero_documento'])) {
                $persona = Persona::withTrashed()->find($id);
                if ($persona) {
                    if ($persona->trashed()) $persona->restore();
                    if ($tipo === 'DEMANDADO' && !$persona->es_demandado) {
                        $persona->update(['es_demandado' => true]);
                    }
                    $ids[] = $persona->id;
                }
                continue; // Saltar el resto de la lógica de creación/sobrescritura
            }

            $nombre = trim($parte['nombre_completo'] ?? '');
            $tipoDoc = $parte['tipo_documento'] ?? 'CC';
            $numeroDoc = trim($parte['numero_documento'] ?? '');
            $dv = $parte['dv'] ?? null;
            $sinInfo = $parte['sin_info'] ?? false;

            if ($sinInfo || (empty($numeroDoc) && $tipo === 'DEMANDADO')) {
                $infoIncompleta = true;
                if ($nombre === '' || $nombre === 'PERSONA INDETERMINADA') {
                    $nombre = "DEMANDADO POR IDENTIFICAR";
                }
                if ($numeroDoc === '') {
                    $numeroDoc = "TEMP-" . ($id ?: uniqid());
                }
            }

            // Buscar si ya existe una persona con ese número de documento (para evitar duplicados al "identificar")
            $personaExistente = null;
            if (!empty($numeroDoc) && !str_starts_with($numeroDoc, 'TEMP-')) {
                $personaExistente = Persona::withTrashed()->where('numero_documento', $numeroDoc)->first();
            }

            if ($personaExistente) {
                if ($personaExistente->trashed()) $personaExistente->restore();
                $personaExistente->update([
                    'nombre_completo' => $nombre ?: $personaExistente->nombre_completo,
                    'tipo_documento'  => $tipoDoc ?: $personaExistente->tipo_documento,
                    'dv'              => $dv ?? $personaExistente->dv,
                    'es_demandado'    => $personaExistente->es_demandado || ($tipo === 'DEMANDADO'),
                ]);
                $persona = $personaExistente;
            } elseif ($id) {
                $persona = Persona::withTrashed()->find($id);
                if ($persona) {
                    if ($persona->trashed()) $persona->restore();
                    $persona->update([
                        'nombre_completo'  => $nombre ?: $persona->nombre_completo,
                        'tipo_documento'   => $tipoDoc ?: $persona->tipo_documento,
                        'numero_documento' => $numeroDoc ?: $persona->numero_documento,
                        'dv'               => $dv ?? $persona->dv,
                        'es_demandado'     => $persona->es_demandado || ($tipo === 'DEMANDADO'),
                    ]);
                }
            } else {
                $persona = Persona::withTrashed()->updateOrCreate(
                    ['numero_documento' => $numeroDoc],
                    [
                        'nombre_completo' => $nombre ?: 'SIN NOMBRE',
                        'tipo_documento'  => $tipoDoc,
                        'dv'              => $dv,
                        'es_demandado'    => ($tipo === 'DEMANDADO'),
                        'deleted_at'      => null
                    ]
                );
            }

            if ($persona) {
                if (!empty($parte['cooperativas_ids'])) $persona->cooperativas()->sync($parte['cooperativas_ids']);
                if (!empty($parte['abogados_ids'])) $persona->abogados()->sync($parte['abogados_ids']);
                $ids[] = $persona->id;
            }
        }

        return ['ids' => $ids, 'info_incompleta' => $infoIncompleta];
    }

    public function exportarExcel(Request $request)
    {
        $this->authorize('viewAny', ProcesoRadicado::class);
        $filtros = $request->all();
        return Excel::download(new ProcesosExport($filtros), 'procesos_' . now()->format('Ymd_His') . '.xlsx');
    }

    public function close(Request $request, ProcesoRadicado $proceso)
    {
        $this->authorize('update', $proceso);
        $validated = $request->validate(['nota_cierre' => 'required|string|max:1000']);
        $proceso->update(['estado' => 'CERRADO', 'nota_cierre' => $validated['nota_cierre']]);
        return back()->with('success', 'Radicado cerrado exitosamente.');
    }

    public function reopen(ProcesoRadicado $proceso)
    {
        $this->authorize('update', $proceso);
        $proceso->update(['estado' => 'ACTIVO', 'nota_cierre' => null]);
        return back()->with('success', 'Radicado reabierto.');
    }

    public function storeActuacion(Request $request, ProcesoRadicado $proceso)
    {
        $this->authorize('update', $proceso);
        $validated = $request->validate([
            'nota' => 'required|string', 
            'fecha_actuacion' => 'required|date',
            'fecha_proxima_revision' => 'required|date|after_or_equal:today',
        ]);

        DB::transaction(function () use ($proceso, $validated) {
            $proceso->actuaciones()->create([
                'nota' => $validated['nota'], 
                'fecha_actuacion' => $validated['fecha_actuacion'], 
                'user_id' => Auth::id()
            ]);

            $proceso->update(['fecha_proxima_revision' => $validated['fecha_proxima_revision']]);
        });

        return back()->with('success', 'Actuación registrada y próxima revisión actualizada.');
    }

    public function updateActuacion(Request $request, Actuacion $actuacion)
    {
        $proceso = $actuacion->actuable;
        if ($proceso instanceof ProcesoRadicado) {
            $this->authorize('update', $proceso);
        }
        $validated = $request->validate(['nota' => 'required|string', 'fecha_actuacion' => 'required|date']);
        $actuacion->update($validated);
        return back()->with('success', 'Actuación actualizada.');
    }

    public function destroyActuacion(Actuacion $actuacion)
    {
        $proceso = $actuacion->actuable;
        if ($proceso instanceof ProcesoRadicado) {
            $this->authorize('update', $proceso);
        }
        $actuacion->delete();
        return back()->with('success', 'Actuación eliminada.');
    }
    
    public function showImportForm() {
        $this->authorize('create', ProcesoRadicado::class);
        return Inertia::render('Radicados/Import');
    }

    public function handleImport(Request $request) {
        $this->authorize('create', ProcesoRadicado::class);
        $request->validate(['archivo' => 'required|mimes:xlsx,xls,csv|max:10240']);
        try {
            Excel::import(new ProcesosImport, $request->file('archivo'));
            return to_route('procesos.index')->with('success', 'Importación completada exitosamente.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->failures())->with('error', 'Error en los datos del archivo.');
        } catch (\Exception $e) {
            return back()->with('error', 'Error crítico: ' . $e->getMessage());
        }
    }
}
