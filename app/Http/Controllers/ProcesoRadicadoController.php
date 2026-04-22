<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use App\Models\ProcesoRadicado;
use App\Http\Requests\StoreProcesoRadicadoRequest;
use App\Http\Requests\UpdateProcesoRadicadoRequest;
use App\Models\User;
use App\Models\AuditoriaEvento;
use App\Models\EtapaProcesal;
use App\Models\Juzgado;
use App\Models\TipoProceso;
use App\Models\Cooperativa;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ProcesosExport;
use App\Models\Actuacion;
use App\Traits\RegistraRevisionTrait;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ProcesoRadicadoController extends Controller
{
    use RegistraRevisionTrait, AuthorizesRequests;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ProcesoRadicado::class);
        $query = ProcesoRadicado::with(['abogado:id,name', 'responsableRevision:id,name', 'juzgado:id,nombre', 'tipoProceso:id,nombre', 'demandantes', 'demandados', 'etapaActual:id,nombre,riesgo']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('radicado', 'ilike', "%{$search}%")->orWhere('asunto', 'ilike', "%{$search}%")
                  ->orWhereHas('demandantes', fn($sq) => $sq->where('nombre_completo', 'ilike', "%{$search}%"))
                  ->orWhereHas('demandados', fn($sq) => $sq->where('nombre_completo', 'ilike', "%{$search}%"));
            });
        }

        if ($request->boolean('sin_radicado')) { $query->where(function($q) { $q->whereNull('radicado')->orWhere('radicado', ''); }); }
        if ($request->filled('estado') && $request->estado !== 'TODOS') { $query->where('estado', $request->estado); }
        if ($request->filled('juzgado_id')) { $query->where('juzgado_id', $request->juzgado_id); }
        if ($request->filled('tipo_entidad')) {
            $tipo = $request->input('tipo_entidad');
            $query->whereHas('juzgado', fn($q) => $q->where('nombre', 'ilike', "%{$tipo}%"));
        }

        // --- Estadísticas para los KPI Cards ---
        $statsQuery = (clone $query)->reorder();
        $stats = [
            'total' => (clone $statsQuery)->count(),
            'sin_radicado' => (clone $statsQuery)->where(fn($q) => $q->whereNull('radicado')->orWhere('radicado', ''))->count(),
            'vencidos' => (clone $statsQuery)->where('fecha_proxima_revision', '<', now()->toDateString())->where('estado', 'ACTIVO')->count(),
            'revisar_hoy' => (clone $statsQuery)->where('fecha_proxima_revision', now()->toDateString())->where('estado', 'ACTIVO')->count(),
        ];

        return Inertia::render('Radicados/Index', [
            'procesos' => $query->orderBy('is_pinned', 'desc')->latest('updated_at')->paginate(15)->withQueryString(),
            'filtros'  => $request->all(),
            'selectedJuzgado' => $request->filled('juzgado_id') ? Juzgado::find($request->juzgado_id, ['id', 'nombre']) : null,
            'juzgados' => [], // Enviamos vacío para evitar lentitud, el select ahora será asíncrono
            'stats'    => $stats,
        ]);
    }

    public function exportarExcel(Request $request)
    {
        $fecha = date('Y-m-d_H-i');
        return Excel::download(new ProcesosExport($request->all()), "Export_Radicados_{$fecha}.xlsx");
    }

    public function togglePin(ProcesoRadicado $proceso)
    {
        $this->authorize('update', $proceso);
        $proceso->update(['is_pinned' => !$proceso->is_pinned]);
        
        return back()->with('success', $proceso->is_pinned ? 'Proceso fijado correctamente.' : 'Proceso desfijado.');
    }

    public function quickReview(ProcesoRadicado $proceso)
    {
        $this->authorize('update', $proceso);
        
        // Actualizamos fecha de revisión hoy y sumamos 15 días para la próxima
        $proceso->update([
            'fecha_revision' => now(),
            'fecha_proxima_revision' => now()->addDays(15)
        ]);

        // Registro en Auditoría
        $proceso->auditoria()->create([
            'user_id' => Auth::id(),
            'evento' => 'REVISIÓN_RÁPIDA',
            'descripcion_breve' => 'El abogado marcó el proceso como revisado mediante acción rápida. Próxima revisión en 15 días.',
            'criticidad' => 'baja',
            'direccion_ip' => request()->ip()
        ]);

        return back()->with('success', 'Expediente marcado como revisado. Próxima revisión en 15 días.');
    }

    public function updateChecklist(Request $request, ProcesoRadicado $proceso)
    {
        $this->authorize('update', $proceso);
        $proceso->update(['checklist_seguimiento' => $request->input('checklist', [])]);
        return back()->with('success', 'Checklist actualizado.');
    }

    public function downloadTemplate()
    {
        return Excel::download(new ProcesosExport(['template' => true]), 'Plantilla_Radicados.xlsx');
    }

    public function importForm(): Response
    {
        $this->authorize('create', ProcesoRadicado::class);
        return Inertia::render('Radicados/Import', [
            'abogados' => User::orderBy('name')->get(['id', 'name']),
            'tiposProceso' => TipoProceso::all(['id', 'nombre']),
            'etapas' => EtapaProcesal::orderBy('orden')->get(['id', 'nombre']),
        ]);
    }

    public function importValidate(Request $request)
    {
        $this->authorize('create', ProcesoRadicado::class);
        $request->validate(['file' => 'required|mimes:xlsx,xls']);

        $import = new \App\Imports\ProcesosImport;
        Excel::import($import, $request->file('file'));
        $rows = $import->getProcessedRows();
        
        foreach ($rows as &$row) {
            $row['messages'] = [];
            $row['status'] = 'success';

            // 1. Identificación
            $existente = null;
            if (!empty($row['id_interno'])) $existente = ProcesoRadicado::find($row['id_interno']);
            if (!$existente && !empty($row['radicado'])) $existente = ProcesoRadicado::where('radicado', $row['radicado'])->first();

            if ($existente) {
                $row['id_interno'] = $existente->id;
                $row['messages'][] = "Identificado: Expediente #{$existente->id}.";
                
                // --- COMPARACIÓN PASIVA (Solo si el Excel trae datos) ---
                $diffs = [];
                $check = function($field, $excelVal, $dbVal) use (&$diffs) {
                    if ($excelVal === null) return; // Si no hay nada en Excel, ignoramos
                    $ev = trim((string)$excelVal);
                    $dv = trim((string)($dbVal ?? ''));
                    if ($ev !== $dv && $ev !== '') {
                        $diffs[] = "{$field}: '{$dv}' -> '{$ev}'";
                    }
                };

                $check('Asunto', $row['asunto'] ?? null, $existente->asunto);
                $check('Naturaleza', $row['naturaleza'] ?? null, $existente->naturaleza);
                $check('Estado', $row['estado'] ?? null, $existente->estado);
                
                if (!empty($diffs)) {
                    $row['status'] = 'warning';
                    $row['messages'][] = "CAMBIOS DETECTADOS: " . implode(" | ", $diffs);
                } else {
                    $row['messages'][] = "DATOS IDÉNTICOS: No se realizará ningún cambio.";
                }

            } else {
                $row['messages'][] = "NUEVO: Se registrará un nuevo expediente.";
            }

            // 2. Conflictos de Unicidad (Advertencia para no bloquear carga en producción)
            if (!empty($row['radicado']) && strlen($row['radicado']) === 23) {
                $conflicto = ProcesoRadicado::where('radicado', $row['radicado'])
                    ->where('id', '!=', $row['id_interno'])
                    ->first();
                
                if ($conflicto) {
                    $row['status'] = 'warning';
                    $row['messages'][] = "AVISO: Este radicado ya está asignado al Exp #{$conflicto->id}. Se mantendrá el duplicado si procesa la carga.";
                }
            }
        }
        
        return response()->json([
            'rows' => $rows,
            'total_rows' => count($rows),
            'warning_rows' => collect($rows)->where('status', 'warning')->count(),
            'error_rows' => collect($rows)->where('status', 'error')->count()
        ]);
    }

    public function importStore(Request $request)
    {
        $this->authorize('create', ProcesoRadicado::class);
        $data = $request->input('data', []);
        $globalAbogadoId = $request->input('abogado_id', Auth::id());

        DB::transaction(function () use ($data, $globalAbogadoId) {
            foreach ($data as $row) {
                $existente = null;
                if (!empty($row['id_interno'])) $existente = ProcesoRadicado::find($row['id_interno']);
                if (!$existente && !empty($row['radicado'])) $existente = ProcesoRadicado::where('radicado', $row['radicado'])->first();

                // Resolver relaciones
                $juzgadoId = $existente?->juzgado_id;
                if (!empty($row['juzgado_nombre'])) {
                    $juzgadoId = Juzgado::firstOrCreate(['nombre' => trim($row['juzgado_nombre'])])->id;
                }

                $tipoId = $existente?->tipo_proceso_id;
                if (!empty($row['tipo_proceso_nombre'])) {
                    $tipoId = TipoProceso::firstOrCreate(['nombre' => trim($row['tipo_proceso_nombre'])])->id;
                }

                $etapaId = $existente?->etapa_procesal_id;
                if (!empty($row['etapa_nombre'])) {
                    $etapa = EtapaProcesal::where('nombre', 'ilike', trim($row['etapa_nombre']))->first();
                    if ($etapa) $etapaId = $etapa->id;
                }

                $abogadoId = $existente?->abogado_id ?: $globalAbogadoId;
                if (!empty($row['abogado_gestor'])) {
                    $u = User::where('name', 'ilike', trim($row['abogado_gestor']))->first();
                    if ($u) $abogadoId = $u->id;
                }

                // --- VALIDACIÓN PREVIA DE UNICIDAD (Para evitar bloqueo de transacción en PostgreSQL) ---
                $radicadoDestino = $row['radicado'] ?? ($existente?->radicado);
                if (!empty($radicadoDestino)) {
                    $idActual = $row['id_interno'] ?? ($existente?->id ?? 0);
                    $existeEnOtro = ProcesoRadicado::where('radicado', $radicadoDestino)
                        ->where('id', '!=', $idActual)
                        ->exists();
                    
                    if ($existeEnOtro) {
                        // Si el radicado ya existe en otro expediente, mantenemos el que ya tiene el registro
                        // para evitar el error de Unique Violation que rompe la transacción.
                        $radicadoDestino = $existente?->radicado;
                        Log::info("Evitando duplicado de radicado para ID {$idActual}. Se mantiene el original.");
                    }
                }

                $procesoData = [
                    'radicado' => $radicadoDestino,
                    'fecha_radicado' => $row['fecha_radicado'] ?? ($existente?->fecha_radicado),
                    'naturaleza' => $row['naturaleza'] ?? ($existente?->naturaleza),
                    'asunto' => $row['asunto'] ?? ($existente?->asunto),
                    'tipo_proceso_id' => $tipoId,
                    'juzgado_id' => $juzgadoId,
                    'etapa_procesal_id' => $etapaId,
                    'abogado_id' => $abogadoId,
                    'estado' => $row['estado'] ?? ($existente?->estado ?: 'ACTIVO'),
                    'info_incompleta' => $row['info_incompleta'] ?? ($existente?->info_incompleta ?? false),
                    'observaciones' => $row['observaciones'] ?? ($existente?->observaciones),
                    'link_expediente' => $row['link_expediente'] ?? ($existente?->link_expediente),
                    'ubicacion_drive' => $row['ubicacion_drive'] ?? ($existente?->ubicacion_drive),
                    'fecha_proxima_revision' => $row['fecha_proxima_revision'] ?? ($existente?->fecha_proxima_revision),
                ];

                if (!$existente) {
                    $procesoData['created_by'] = Auth::id();
                    $procesoData['fecha_cambio_etapa'] = now();
                }

                $proceso = ProcesoRadicado::updateOrCreate(['id' => $row['id_interno'] ?? ($existente?->id ?? 0)], $procesoData);
                
                if (!empty($row['demandantes_nombres'])) $this->procesarPartes($proceso, $row['demandantes_nombres'], 'DEMANDANTE');
                if (!empty($row['demandados_nombres'])) $this->procesarPartes($proceso, $row['demandados_nombres'], 'DEMANDADO');
            }
        });
        return to_route('procesos.index')->with('success', 'Importación exitosa.');
    }

    private function procesarPartes($proceso, $raw, $tipo)
    {
        if (empty($raw)) return;
        $items = explode(';', $raw); $ids = [];
        foreach ($items as $item) {
            $item = trim($item); if (empty($item)) continue;
            $doc = null; $tipoDoc = 'CC'; $nombre = $item;
            
            // Regex mejorado para soportar IDs alfanuméricos (como TEMP-...)
            if (preg_match('/\[ID:\s*([A-Z]+)\s*([A-Z0-9-]+).*\]/i', $item, $m)) { 
                $tipoDoc = $m[1]; 
                $doc = $m[2]; 
                $nombre = trim(explode('[', $item)[0]); 
            }
            
            if ($doc) { 
                $persona = Persona::withTrashed()->updateOrCreate(
                    ['numero_documento' => $doc], 
                    ['nombre_completo' => $nombre, 'tipo_documento' => $tipoDoc, 'deleted_at' => null]
                ); 
            } else { 
                // Si no hay documento, buscamos por nombre pero evitamos crear si falta el documento obligatorio
                $persona = Persona::where('nombre_completo', $nombre)->first();
                if (!$persona) {
                    // Si es una persona nueva y no tiene documento, le asignamos uno temporal para evitar el error de BD
                    $tempId = 'TEMP-' . substr(md5($nombre . time()), 0, 12);
                    $persona = Persona::create([
                        'nombre_completo' => $nombre,
                        'tipo_documento' => 'CC',
                        'numero_documento' => $tempId
                    ]);
                }
            }
            $ids[] = $persona->id;
        }
        $proceso->belongsToMany(Persona::class, 'proceso_radicado_personas')
            ->wherePivot('tipo', $tipo)
            ->syncWithPivotValues($ids, ['tipo' => $tipo], false);
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
        
        return DB::transaction(function() use ($request) {
            $data = $request->validated(); 
            $data['created_by'] = Auth::id(); 
            $data['fecha_cambio_etapa'] = now(); 
            
            $proceso = ProcesoRadicado::create($data); 
            
            $this->syncPersonasFrontend($proceso, $request->input('demandantes', []), $request->input('demandados', []));

            // Auditoría con detalles humanizados
            $detalleNuevo = $proceso->getRawOriginal();
            foreach ($detalleNuevo as $key => $val) {
                if (str_ends_with($key, '_id') && !empty($val)) {
                    try {
                        if (in_array($key, ['abogado_id', 'responsable_revision_id', 'created_by'])) {
                            $detalleNuevo[$key] = \App\Models\User::find($val)?->name ?? $val;
                        } elseif ($key === 'juzgado_id') {
                            $detalleNuevo[$key] = \App\Models\Juzgado::find($val)?->nombre ?? $val;
                        } elseif ($key === 'tipo_proceso_id') {
                            $detalleNuevo[$key] = \App\Models\TipoProceso::find($val)?->nombre ?? $val;
                        } elseif ($key === 'etapa_procesal_id') {
                            $detalleNuevo[$key] = \App\Models\EtapaProcesal::find($val)?->nombre ?? $val;
                        }
                    } catch (\Exception $e) {}
                }
            }

            AuditoriaEvento::create([
                'user_id' => Auth::id(),
                'evento' => 'CREAR_RADICADO',
                'descripcion_breve' => "Se creó el expediente judicial: " . ($proceso->radicado ?: "ID #{$proceso->id}"),
                'auditable_id' => $proceso->id,
                'auditable_type' => ProcesoRadicado::class,
                'criticidad' => 'media',
                'detalle_nuevo' => $detalleNuevo,
                'direccion_ip' => request()->ip()
            ]);
            
            return to_route('procesos.show', $proceso->id)->with('success', 'Radicado creado correctamente.'); 
        });
    }

    public function show(ProcesoRadicado $proceso): Response 
    { 
        $this->authorize('view', $proceso); 
        $proceso->load(['abogado', 'responsableRevision', 'juzgado', 'tipoProceso', 'demandantes', 'demandados', 'etapaActual', 'actuaciones.user', 'documentos', 'contrato']); 
        
        return Inertia::render('Radicados/Show', [
            'proceso' => $proceso, 
            'etapas' => EtapaProcesal::orderBy('orden')->get(['id', 'nombre']),
            'auditoria' => $proceso->auditoria()->with('usuario:id,name')->latest()->take(50)->get(),
        ]); 
    }
    public function edit(ProcesoRadicado $proceso): Response { $this->authorize('update', $proceso); $proceso->load(['demandantes', 'demandados', 'abogado', 'responsableRevision', 'juzgado', 'tipoProceso']); return Inertia::render('Radicados/Edit', ['proceso' => $proceso, 'etapas' => EtapaProcesal::orderBy('orden')->get(['id', 'nombre'])]); }
    
    public function update(UpdateProcesoRadicadoRequest $request, ProcesoRadicado $proceso) 
    { 
        $this->authorize('update', $proceso); 
        
        return DB::transaction(function() use ($request, $proceso) {
            $original = $proceso->getRawOriginal();
            $proceso->update($request->validated()); 
            $changes = $proceso->getChanges();
            
            $this->syncPersonasFrontend($proceso, $request->input('demandantes', []), $request->input('demandados', []));

            // Auditoría con detalles humanizados
            $anterior = [];
            $nuevo = [];
            foreach ($changes as $key => $val) {
                if (in_array($key, ['updated_at'])) continue;
                
                $oldVal = $original[$key] ?? null;
                $newVal = $val;

                if (str_ends_with($key, '_id') && !empty($val)) {
                    try {
                        if (in_array($key, ['abogado_id', 'responsable_revision_id', 'created_by'])) {
                            $oldVal = \App\Models\User::find($oldVal)?->name ?? $oldVal;
                            $newVal = \App\Models\User::find($newVal)?->name ?? $newVal;
                        } elseif ($key === 'juzgado_id') {
                            $oldVal = \App\Models\Juzgado::find($oldVal)?->nombre ?? $oldVal;
                            $newVal = \App\Models\Juzgado::find($newVal)?->nombre ?? $newVal;
                        } elseif ($key === 'tipo_proceso_id') {
                            $oldVal = \App\Models\TipoProceso::find($oldVal)?->nombre ?? $oldVal;
                            $newVal = \App\Models\TipoProceso::find($newVal)?->nombre ?? $newVal;
                        } elseif ($key === 'etapa_procesal_id') {
                            $oldVal = \App\Models\EtapaProcesal::find($oldVal)?->nombre ?? $oldVal;
                            $newVal = \App\Models\EtapaProcesal::find($newVal)?->nombre ?? $newVal;
                        }
                    } catch (\Exception $e) {}
                }
                $anterior[$key] = $oldVal;
                $nuevo[$key] = $newVal;
            }

            AuditoriaEvento::create([
                'user_id' => Auth::id(),
                'evento' => 'ACTUALIZAR_RADICADO',
                'descripcion_breve' => "Se actualizaron los datos del expediente: " . ($proceso->radicado ?: "ID #{$proceso->id}"),
                'auditable_id' => $proceso->id,
                'auditable_type' => ProcesoRadicado::class,
                'criticidad' => 'baja',
                'detalle_anterior' => $anterior,
                'detalle_nuevo' => $nuevo,
                'direccion_ip' => request()->ip()
            ]);
            
            return to_route('procesos.show', $proceso->id)->with('success', 'Radicado actualizado correctamente.'); 
        });
    }

    public function updateEtapa(Request $request, ProcesoRadicado $proceso)
    {
        $this->authorize('update', $proceso);
        $request->validate([
            'etapa_procesal_id' => 'required|exists:etapas_procesales,id',
            'observacion' => 'nullable|string',
            'fecha_proxima_revision' => 'required|date'
        ]);

        $etapaAnterior = $proceso->etapaActual?->nombre ?: 'Sin etapa';
        $proceso->update([
            'etapa_procesal_id' => $request->etapa_procesal_id,
            'fecha_cambio_etapa' => now(),
            'fecha_proxima_revision' => $request->fecha_proxima_revision
        ]);
        $etapaNueva = EtapaProcesal::find($request->etapa_procesal_id)->nombre;

        // Registrar en Auditoría
        $proceso->auditoria()->create([
            'user_id' => Auth::id(),
            'evento' => 'CAMBIO_ETAPA',
            'descripcion_breve' => "Cambio de etapa: {$etapaAnterior} -> {$etapaNueva}",
            'criticidad' => 'media',
            'direccion_ip' => request()->ip(),
            'detalle_nuevo' => ['observacion' => $request->observacion]
        ]);

        return back()->with('success', 'Etapa procesal actualizada.');
    }

    public function storeActuacion(Request $request, ProcesoRadicado $proceso)
    {
        $this->authorize('update', $proceso);
        $validated = $request->validate([
            'nota' => ['required', 'string'],
            'fecha_actuacion' => ['required', 'date'],
            'fecha_proxima_revision' => ['required', 'date']
        ]);

        $proceso->actuaciones()->create([
            'nota' => $validated['nota'],
            'fecha_actuacion' => $validated['fecha_actuacion'],
            'user_id' => Auth::id()
        ]);

        // Actualizar fecha de revisión obligatoria
        $proceso->update(['fecha_proxima_revision' => $validated['fecha_proxima_revision']]);

        // Auditoría
        $proceso->auditoria()->create([
            'user_id' => Auth::id(),
            'evento' => 'NUEVA_ACTUACION',
            'descripcion_breve' => "Se registró una actuación en el proceso",
            'criticidad' => 'baja',
            'direccion_ip' => request()->ip()
        ]);

        return back()->with('success', 'Actuación registrada.');
    }

    public function updateActuacion(Request $request, Actuacion $actuacion)
    {
        // El permiso se verifica sobre el modelo que recibe la actuación
        $proceso = $actuacion->actuable;
        if (!$proceso || get_class($proceso) !== ProcesoRadicado::class) {
            abort(403);
        }
        $this->authorize('update', $proceso);

        $validated = $request->validate(['nota' => ['required', 'string'], 'fecha_actuacion' => ['required', 'date']]);
        $actuacion->update($validated);

        return back(303)->with('success', 'Actuación actualizada.');
    }

    public function destroyActuacion(Actuacion $actuacion) 
    { 
        $proceso = $actuacion->actuable;
        if (!$proceso || get_class($proceso) !== ProcesoRadicado::class) {
            abort(403);
        }
        $this->authorize('update', $proceso);

        $actuacion->delete(); 
        return back(303)->with('success', 'Actuación eliminada.'); 
    }

    /**
     * Sincroniza las personas (demandantes y demandados) enviadas desde el frontend.
     */
    private function syncPersonasFrontend(ProcesoRadicado $proceso, array $demandantes, array $demandados)
    {
        $syncData = [];
        $firstDemandanteId = null;
        $firstDemandadoId = null;
        
        // 1. Procesar Demandantes
        foreach ($demandantes as $d) {
            $personaId = $d['id'] ?? ($d['selected']['id'] ?? null);
            
            if (!empty($d['is_new'])) {
                // Si es nuevo o estamos editando uno incompleto sin ID aún
                $persona = Persona::withTrashed()->updateOrCreate(
                    ['id' => $personaId],
                    [
                        'nombre_completo' => $d['nombre_completo'],
                        'tipo_documento' => $d['tipo_documento'] ?? 'CC',
                        'numero_documento' => $d['numero_documento'],
                        'dv' => $d['dv'] ?? null,
                        'deleted_at' => null
                    ]
                );
                if (!empty($d['cooperativas_ids'])) $persona->cooperativas()->sync($d['cooperativas_ids']);
                if (!empty($d['abogados_ids'])) $persona->abogados()->sync($d['abogados_ids']);
                $personaId = $persona->id;
            }

            if ($personaId) {
                $syncData[$personaId] = ['tipo' => 'DEMANDANTE'];
                if (!$firstDemandanteId) $firstDemandanteId = $personaId;
            }
        }
        
        // 2. Procesar Demandados
        foreach ($demandados as $d) {
            $personaId = $d['id'] ?? ($d['selected']['id'] ?? null);

            if (!empty($d['is_new'])) {
                $numDoc = $d['numero_documento'] ?? null;
                if (!empty($d['sin_info']) && empty($numDoc)) {
                    $numDoc = 'TEMP-' . substr(md5(uniqid()), 0, 12);
                }
                
                $persona = Persona::withTrashed()->updateOrCreate(
                    ['id' => $personaId],
                    [
                        'nombre_completo' => $d['nombre_completo'],
                        'tipo_documento' => $d['tipo_documento'] ?? 'CC',
                        'numero_documento' => $numDoc,
                        'dv' => $d['dv'] ?? null,
                        'deleted_at' => null
                    ]
                );
                if (!empty($d['cooperativas_ids'])) $persona->cooperativas()->sync($d['cooperativas_ids']);
                if (!empty($d['abogados_ids'])) $persona->abogados()->sync($d['abogados_ids']);
                $personaId = $persona->id;
            }

            if ($personaId) {
                $syncData[$personaId] = ['tipo' => 'DEMANDADO'];
                if (!$firstDemandadoId) $firstDemandadoId = $personaId;
            }
        }
        
        $proceso->personas()->sync($syncData);

        // Actualizar columnas directas para compatibilidad
        $proceso->update([
            'demandante_id' => $firstDemandanteId,
            'demandado_id' => $firstDemandadoId
        ]);
    }
    
    public function destroy(ProcesoRadicado $proceso) 
    { 
        $this->authorize('delete', $proceso); 
        
        $radicado = $proceso->radicado ?: "ID #{$proceso->id}";
        
        AuditoriaEvento::create([
            'user_id' => Auth::id(),
            'evento' => 'SUSPENDER_RADICADO',
            'descripcion_breve' => "Proceso judicial suspendido (movido a papelera): {$radicado}",
            'auditable_id' => $proceso->id,
            'auditable_type' => ProcesoRadicado::class,
            'criticidad' => 'alta',
            'direccion_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        $proceso->delete(); 
        return to_route('procesos.index')->with('success', 'El proceso ha sido suspendido y movido a la papelera.'); 
    }

    public function close(Request $request, ProcesoRadicado $proceso) { $proceso->update(['estado' => 'CERRADO', 'nota_cierre' => $request->nota_cierre]); return back()->with('success', 'Cerrado.'); }
    public function reopen(ProcesoRadicado $proceso) { $proceso->update(['estado' => 'ACTIVO', 'nota_cierre' => null]); return back()->with('success', 'Reabierto.'); }
}
