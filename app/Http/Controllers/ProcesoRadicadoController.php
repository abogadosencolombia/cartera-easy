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
        if ($request->filled('tipo_entidad')) {
            $tipo = $request->input('tipo_entidad');
            $query->whereHas('juzgado', fn($q) => $q->where('nombre', 'ilike', "%{$tipo}%"));
        }

        return Inertia::render('Radicados/Index', [
            'procesos' => $query->latest('updated_at')->paginate(15)->withQueryString(),
            'filtros'  => $request->all(),
        ]);
    }

    public function exportarExcel(Request $request)
    {
        $fecha = date('Y-m-d_H-i');
        return Excel::download(new ProcesosExport($request->all()), "Export_Radicados_{$fecha}.xlsx");
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

    public function create(): Response { $this->authorize('create', ProcesoRadicado::class); return Inertia::render('Radicados/Create', ['etapas' => EtapaProcesal::orderBy('orden')->get(['id', 'nombre'])]); }
    public function store(StoreProcesoRadicadoRequest $request) { $this->authorize('create', ProcesoRadicado::class); $data = $request->validated(); $data['created_by'] = Auth::id(); $data['fecha_cambio_etapa'] = now(); $proceso = ProcesoRadicado::create($data); return to_route('procesos.show', $proceso->id)->with('success', 'Radicado creado.'); }
    public function show(ProcesoRadicado $proceso): Response { $this->authorize('view', $proceso); $proceso->load(['abogado', 'responsableRevision', 'juzgado', 'tipoProceso', 'demandantes', 'demandados', 'etapaActual', 'actuaciones.user', 'documentos']); return Inertia::render('Radicados/Show', ['proceso' => $proceso, 'etapas' => EtapaProcesal::orderBy('orden')->get(['id', 'nombre'])]); }
    public function edit(ProcesoRadicado $proceso): Response { $this->authorize('update', $proceso); $proceso->load(['demandantes', 'demandados', 'abogado', 'responsableRevision', 'juzgado', 'tipoProceso']); return Inertia::render('Radicados/Edit', ['proceso' => $proceso, 'etapas' => EtapaProcesal::orderBy('orden')->get(['id', 'nombre'])]); }
    public function update(UpdateProcesoRadicadoRequest $request, ProcesoRadicado $proceso) { $this->authorize('update', $proceso); $proceso->update($request->validated()); return to_route('procesos.show', $proceso->id)->with('success', 'Actualizado.'); }
    public function destroy(ProcesoRadicado $proceso) { $this->authorize('delete', $proceso); $proceso->delete(); return to_route('procesos.index')->with('success', 'Eliminado.'); }
    public function close(Request $request, ProcesoRadicado $proceso) { $proceso->update(['estado' => 'CERRADO', 'nota_cierre' => $request->nota_cierre]); return back()->with('success', 'Cerrado.'); }
    public function reopen(ProcesoRadicado $proceso) { $proceso->update(['estado' => 'ACTIVO', 'nota_cierre' => null]); return back()->with('success', 'Reabierto.'); }
}
