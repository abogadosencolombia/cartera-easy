<?php

namespace App\Http\Controllers;

use App\Models\Caso;
use App\Models\Cooperativa;
use App\Models\Persona;
use App\Models\Codeudor;
use App\Models\User;
use App\Models\Juzgado;
use App\Models\PlantillaDocumento;
use App\Models\EspecialidadJuridica;
use App\Models\TipoProceso;
use App\Models\RequisitoDocumento;
use App\Models\AuditoriaEvento;
use App\Http\Requests\StoreCasoRequest;
use App\Http\Requests\UpdateCasoRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Models\Actuacion;
use App\Models\Contrato;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\CasosExport;
use App\Traits\RegistraRevisionTrait;

class CasoController extends Controller
{
    use AuthorizesRequests, RegistraRevisionTrait;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Caso::class);
        $user = Auth::user();

        $query = Caso::with([
            'cooperativa', 
            'deudor:id,nombre_completo,numero_documento,celular_1,correo_1,celular_2,correo_2', 
            'codeudores:id,nombre_completo,numero_documento',
            'especialidad:id,nombre',
            'user', 
            'users', 
            'juzgado'
        ]);

        // --- 1. Filtros por Rol de Usuario (Seguridad) ---
        if ($user->tipo_usuario === 'admin') {
            // Admin ve todo
        } elseif (in_array($user->tipo_usuario, ['gestor', 'abogado'])) {
            $query->where(function($q) use ($user) {
                $cooperativaIds = $user->cooperativas->pluck('id');
                
                // Ve casos de sus cooperativas
                $q->whereIn('cooperativa_id', $cooperativaIds)
                  // O casos donde es el abogado principal
                  ->orWhere('user_id', $user->id)
                  // O casos donde está asignado en la relación múltiple
                  ->orWhereHas('users', function($uq) use ($user) {
                      $uq->where('users.id', $user->id);
                  });
            });
        } elseif ($user->tipo_usuario === 'cliente') {
            $query->where('deudor_id', $user->persona_id);
        }

        // --- 2. Filtro por Abogado/Gestor (Soporte múltiples) ---
        $query->when($request->input('abogado_id'), function ($q, $abogadoId) {
            $q->whereHas('users', function($uq) use ($abogadoId) {
                $uq->where('users.id', $abogadoId);
            });
        });

        // --- 3. Filtro por Cooperativa ---
        $query->when($request->input('cooperativa_id'), function ($q, $cooperativaId) {
            $q->where('cooperativa_id', $cooperativaId);
        });

        // --- 4. Filtro por Etapa Procesal ---
        $query->when($request->input('etapa_procesal'), function ($q, $etapa) {
            $q->where('etapa_procesal', $etapa);
        });

        // --- 5. Filtro de Búsqueda General (Multi-palabra inteligente) ---
        $query->when($request->input('search'), function ($q, $search) {
            $words = array_filter(explode(' ', trim($search)));

            foreach ($words as $word) {
                $cleanWord = preg_replace('/[^0-9]/', '', $word);
                
                $q->where(function ($subq) use ($word, $cleanWord) {
                    $subq->where('tipo_proceso', 'ilike', "%{$word}%")
                        ->orWhere('referencia_credito', 'ilike', "%{$word}%")
                        ->orWhere('radicado', 'ilike', "%{$word}%")
                        ->orWhere('subtipo_proceso', 'ilike', "%{$word}%")
                        ->orWhere('subproceso', 'ilike', "%{$word}%")
                        ->orWhere('etapa_procesal', 'ilike', "%{$word}%");
                    
                    if ($cleanWord) {
                        $subq->orWhereRaw("regexp_replace(radicado, '[^0-9]', '', 'g') ILIKE ?", ["%{$cleanWord}%"])
                             ->orWhere('referencia_credito', 'ilike', "%{$cleanWord}%");
                    }

                    $subq->orWhereHas('deudor', function ($deudorQuery) use ($word, $cleanWord) {
                            $deudorQuery->where('nombre_completo', 'ilike', "%{$word}%");
                            if ($cleanWord) {
                                $deudorQuery->orWhere('numero_documento', 'ilike', "%{$cleanWord}%");
                            }
                        })
                        ->orWhereHas('codeudores', function ($codeudorQuery) use ($word, $cleanWord) {
                            $codeudorQuery->where('nombre_completo', 'ilike', "%{$word}%");
                            if ($cleanWord) {
                                $codeudorQuery->orWhere('numero_documento', 'ilike', "%{$cleanWord}%");
                            }
                        })
                        ->orWhereHas('juzgado', function ($juzgadoQuery) use ($word) {
                            $juzgadoQuery->where('nombre', 'ilike', "%{$word}%");
                        })
                        ->orWhereHas('cooperativa', function ($coopQuery) use ($word) {
                            $coopQuery->where('nombre', 'ilike', "%{$word}%");
                        });
                });
            }
        });

        // --- 6. Filtro por Juzgado ---
        $query->when($request->input('juzgado_id'), function ($q, $juzgadoId) {
            $q->where('juzgado_id', $juzgadoId);
        });

        // --- 7. Filtro por Tipo de Entidad ---
        $query->when($request->input('tipo_entidad'), function ($q, $tipo) {
            $q->whereHas('juzgado', function($jq) use ($tipo) {
                $jq->where('nombre', 'ilike', "%{$tipo}%");
            });
        });

        if ($request->boolean('sin_radicado')) {
            $query->where(function ($q) {
                $q->whereNull('radicado')->orWhere('radicado', '');
            });
        }

        if ($request->boolean('inactivo_15_dias')) {
            $query->where('updated_at', '<', now()->subDays(15))
                  ->where('estado_proceso', '!=', 'cerrado');
        }

        $casos = $query->orderBy('is_pinned', 'desc')->orderBy('updated_at', 'desc')->paginate(20)->withQueryString();

        $abogados = [];
        $cooperativas = [];
        if (in_array($user->tipo_usuario, ['admin', 'gestor', 'abogado'])) {
            $abogados = User::whereIn('tipo_usuario', ['abogado', 'gestor'])->select('id', 'name')->orderBy('name')->get();
            $cooperativas = Cooperativa::select('id', 'nombre')->orderBy('nombre')->get();
        }

        $selectedJuzgado = null;
        if ($request->input('juzgado_id')) {
            $selectedJuzgado = Juzgado::find($request->input('juzgado_id'), ['id', 'nombre']);
        }

        $etapas_procesales = DB::table('etapas_procesales')->orderBy('nombre')->pluck('nombre')->all();

        // --- Estadísticas para los KPI Cards (SQL puro para máxima velocidad y alcance global) ---
        $statsQuery = (clone $query)->reorder(); // <--- Eliminamos cualquier ORDER BY heredado
        $stats = Inertia::defer(function() use ($statsQuery) {
            return [
                'total' => (clone $statsQuery)->count(),
                'sin_radicado' => (clone $statsQuery)->where(fn($q) => $q->whereNull('radicado')->orWhere('radicado', ''))->count(),
                'saldo_total' => (clone $statsQuery)->selectRaw('SUM(COALESCE(monto_total, 0) - COALESCE(monto_total_pagado, 0)) as total')->value('total') ?? 0,
                'actualizados_hoy' => (clone $statsQuery)->whereDate('updated_at', Carbon::today())->count(),
            ];
        });

        return Inertia::render('Casos/Index', [
            'casos' => $casos,
            'abogados' => $abogados,
            'cooperativas' => $cooperativas,
            'selectedJuzgado' => $selectedJuzgado,
            'etapas_procesales' => $etapas_procesales,
            'filters' => $request->only(['search', 'abogado_id', 'cooperativa_id', 'juzgado_id', 'tipo_entidad', 'etapa_procesal', 'sin_radicado']),
            'stats' => $stats,
            'can' => ['delete_cases' => true],
        ]);
    }

    public function export(Request $request)
    {
        $fecha = date('Y-m-d_H-i');
        $nombreArchivo = "Reporte_Casos_Completo_{$fecha}.xlsx";
        $filtros = $request->all();

        AuditoriaEvento::create([
            'user_id' => Auth::id(),
            'evento' => 'EXPORTAR_CASOS',
            'descripcion_breve' => "Descarga masiva de casos",
            'criticidad' => 'baja',
            'direccion_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return Excel::download(new CasosExport($filtros), $nombreArchivo);
    }

    public function importForm(): Response
    {
        $this->authorize('create', Caso::class);
        return Inertia::render('Casos/Import', [
            'cooperativas' => Cooperativa::all(['id', 'nombre']),
            'juzgados' => Juzgado::all(['id', 'nombre']),
        ]);
    }

    public function downloadTemplate()
    {
        return Excel::download(new \App\Exports\CasosTemplateExport, 'Plantilla_Importacion_Casos.xlsx');
    }

    public function importValidate(Request $request)
    {
        $this->authorize('create', Caso::class);
        $request->validate(['file' => 'required|mimes:xlsx,xls']);

        $import = new \App\Imports\CasosImport;
        Excel::import($import, $request->file('file'));

        $rows = $import->getProcessedRows();
        
        foreach ($rows as &$row) {
            if ($row['status'] === 'error') continue;

            // 1. Mapear Cooperativa
            $coop = Cooperativa::where('nombre', 'ilike', trim($row['cooperativa_nombre']))->first();
            if ($coop) {
                $row['cooperativa_id'] = $coop->id;
                $row['cooperativa_nombre'] = $coop->nombre;
            } else {
                $row['status'] = 'error';
                $row['messages'][] = "Empresa '{$row['cooperativa_nombre']}' no existe.";
            }

            // 2. Mapear Juzgado y Especialidad
            $row['juzgado_id'] = Juzgado::where('nombre', 'ilike', trim($row['juzgado_nombre']))->value('id');
            $row['especialidad_id'] = EspecialidadJuridica::where('nombre', 'ilike', trim($row['especialidad_nombre']))->value('id');

            // 3. Buscar Deudor (Normalizando documento en DB y Excel)
            $docLimpio = preg_replace('/[^0-9]/', '', $row['documento_deudor']);
            $deudor = Persona::whereRaw("regexp_replace(numero_documento, '[^0-9]', '', 'g') = ?", [$docLimpio])->first();
            
            if (!$deudor) {
                $row['status'] = ($row['status'] === 'error') ? 'error' : 'warning';
                $row['messages'][] = "DEUDOR NUEVO: Se creará un perfil para '{$row['nombre_deudor']}'.";
            } else {
                $row['messages'][] = "Deudor vinculado: {$deudor->nombre_completo}";
            }

            // 4. Buscar Caso Existente para comparar
            $existente = null;
            if (!empty($row['id_sistema'])) {
                $existente = Caso::find($row['id_sistema']);
            }
            
            // --- VALIDACIÓN DE UNICIDAD OBLIGATORIA (PAGARÉ Y RADICADO) ---
            
            // Validar Referencia de Crédito (Pagaré) Único
            if (!empty($row['referencia_credito'])) {
                $conflictoRef = Caso::where('referencia_credito', 'ilike', trim($row['referencia_credito']))
                    ->when($existente, fn($q) => $q->where('id', '!=', $existente->id))
                    ->first();
                
                if ($conflictoRef) {
                    $row['status'] = 'error';
                    $row['messages'][] = "ERROR: El Pagaré '{$row['referencia_credito']}' ya está registrado en el Caso #{$conflictoRef->id}. No puede duplicarse.";
                }
            }

            // Validar Radicado Único
            if (!empty($row['radicado']) && strlen($row['radicado']) === 23) {
                $conflictoRad = Caso::where('radicado', $row['radicado'])
                    ->when($existente, fn($q) => $q->where('id', '!=', $existente->id ?? 0))
                    ->first();
                
                if ($conflictoRad) {
                    $row['status'] = 'error';
                    $row['messages'][] = "ERROR: El Radicado '{$row['radicado']}' ya existe en el Caso #{$conflictoRad->id}.";
                }
            }

            if (!$existente && $row['status'] !== 'error') {
                // Si no hay ID de sistema, intentar emparejar por Radicado o Pagaré para evitar duplicados si no se marcó el error antes
                if (!empty($row['radicado'])) {
                    $existente = Caso::where('radicado', $row['radicado'])->first();
                }
                if (!$existente && $deudor && !empty($row['referencia_credito'])) {
                    $existente = Caso::where('deudor_id', $deudor->id)
                                     ->where('referencia_credito', 'ilike', trim($row['referencia_credito']))
                                     ->first();
                }
            }

            if ($existente && $row['status'] !== 'error') {
                // Sincronizar ID de sistema para el guardado posterior
                $row['id_sistema'] = $existente->id;
                $cambios = [];
                $sonIguales = fn($a, $b) => mb_strtolower(trim((string)$a)) === mb_strtolower(trim((string)$b));
                
                if (abs((float)$existente->monto_total - (float)$row['monto_total']) > 1) $cambios[] = "Monto Inicial";
                if (!$sonIguales($existente->etapa_procesal, $row['etapa_procesal'])) $cambios[] = "Etapa";
                
                // Comparación de Referencia (Numérica si es posible)
                $refExistente = trim((string)$existente->referencia_credito);
                $refExcel = trim((string)$row['referencia_credito']);
                if (is_numeric($refExistente) && is_numeric($refExcel)) {
                    if (abs((float)$refExistente - (float)$refExcel) > 0.1) $cambios[] = "Referencia";
                } elseif (!$sonIguales($refExistente, $refExcel)) {
                    $cambios[] = "Referencia";
                }

                if (!empty($cambios)) {
                    $row['status'] = 'warning';
                    $row['messages'][] = "ACTUALIZACIÓN DETECTADA (ID #{$existente->id}): Se modificará " . implode(", ", $cambios);
                } else {
                    $row['messages'][] = "Sin cambios (Datos idénticos).";
                }
            } else {
                $row['messages'][] = "NUEVO CASO: Se creará un expediente desde cero.";
            }
        }
        
        return response()->json([
            'rows' => $rows,
            'total_rows' => count($rows),
            'valid_rows' => collect($rows)->whereIn('status', ['success', 'warning'])->count(),
            'warning_rows' => collect($rows)->where('status', 'warning')->count(),
            'error_rows' => collect($rows)->where('status', 'error')->count(),
        ]);
    }

    public function importStore(Request $request)
    {
        $this->authorize('create', Caso::class);
        $data = $request->input('data', []);

        DB::transaction(function () use ($data) {
            foreach ($data as $row) {
                // 1. Upsert Persona (Deudor)
                    $deudor = Persona::withTrashed()->where('numero_documento', trim($row['documento_deudor']))->first();
                    if ($deudor) {
                        $deudor->update([
                            'nombre_completo' => trim($row['nombre_deudor']),
                            'tipo_documento' => $row['tipo_documento'] ?? 'CC',
                            'dv' => $row['dv'] ?? null,
                            'celular_1' => $row['celular_deudor'] ?? null,
                            'correo_1' => $row['correo_deudor'] ?? null,
                        ]);
                    } else {
                        $deudor = Persona::create([
                            'nombre_completo' => trim($row['nombre_deudor']),
                            'tipo_documento' => $row['tipo_documento'] ?? 'CC',
                            'numero_documento' => trim($row['documento_deudor']),
                            'dv' => $row['dv'] ?? null,
                            'celular_1' => $row['celular_deudor'] ?? null,
                            'correo_1' => $row['correo_deudor'] ?? null,
                        ]);
                    }

                // 2. Vincular Deudor a Cooperativa (Si se indica)
                if (!empty($row['cooperativa_id'])) {
                    $deudor->cooperativas()->syncWithoutDetaching([$row['cooperativa_id']]);
                }

                // 3. Upsert Caso (Llave: Radicado o Deudor+Referencia)
                $defaultUserId = User::where('name', 'ilike', 'NUBIA AIDE GALLEGO')->value('id') ?? Auth::id();
                
                $casoData = [
                    'user_id' => $defaultUserId, 
                    'deudor_id' => $deudor->id,
                    'cooperativa_id' => $row['cooperativa_id'],
                    'juzgado_id' => $row['juzgado_id'],
                    'especialidad_id' => $row['especialidad_id'],
                    'tipo_proceso' => $row['tipo_proceso'],
                    'subtipo_proceso' => $row['subtipo_proceso'],
                    'subproceso' => $row['subproceso'],
                    'etapa_procesal' => !empty($row['etapa_procesal']) ? $row['etapa_procesal'] : 'INICIAL',
                    'radicado' => $row['radicado'],
                    'referencia_credito' => $row['referencia_credito'],
                    'monto_total' => $row['monto_total'] ?? 0,
                    'monto_deuda_actual' => $row['monto_deuda_actual'] ?? 0,
                    'monto_total_pagado' => $row['monto_total_pagado'] ?? 0,
                    'tasa_interes_corriente' => $row['tasa_interes_corriente'] ?? 0,
                    'fecha_inicio_credito' => $row['fecha_inicio_credito'] ?? now(),
                    'fecha_apertura' => $row['fecha_apertura'] ?? now(),
                    'fecha_vencimiento' => $row['fecha_vencimiento'],
                    'fecha_ultimo_pago' => $row['fecha_ultimo_pago'],
                    'fecha_tasa_interes' => $row['fecha_tasa_interes'],
                    'tipo_garantia_asociada' => !empty($row['tipo_garantia_asociada']) ? $row['tipo_garantia_asociada'] : 'sin garantía',
                    'origen_documental' => !empty($row['origen_documental']) ? $row['origen_documental'] : 'otro',
                    'medio_contacto' => !empty($row['medio_contacto']) ? $row['medio_contacto'] : 'otro',
                    'link_drive' => $row['link_drive'],
                    'link_expediente' => $row['link_expediente'],
                    'notas_legales' => $row['notas_legales'],
                    'nota_cierre' => $row['nota_cierre'],
                    'bloqueado' => $row['bloqueado'] ?? false,
                    'motivo_bloqueo' => $row['motivo_bloqueo'],
                    'estado' => $row['estado'] ?? 'ACTIVO',
                    'estado_proceso' => $row['estado_proceso'],
                ];

                // Si vienen abogados en el Excel, intentamos asignar el primero
                if (!empty($row['abogados_nombres'])) {
                    $primerNombre = trim(explode(',', $row['abogados_nombres'])[0]);
                    $uid = User::where('name', 'ilike', $primerNombre)->value('id');
                    if ($uid) $casoData['user_id'] = $uid;
                }

                $caso = null;
                if (!empty($row['id_sistema'])) {
                    $caso = Caso::updateOrCreate(['id' => $row['id_sistema']], $casoData);
                } elseif (!empty($row['radicado']) && strlen($row['radicado']) === 23) {
                    $caso = Caso::updateOrCreate(['radicado' => $row['radicado']], $casoData);
                } else {
                    // Si no hay radicado ni ID, buscar por deudor y referencia para evitar duplicados
                    $caso = Caso::updateOrCreate(
                        ['deudor_id' => $deudor->id, 'referencia_credito' => $row['referencia_credito']],
                        $casoData
                    );
                }

                // 4. Sincronizar Abogados desde el Excel (si se proporcionan nombres)
                if (!empty($row['abogados_nombres'])) {
                    $nombres = explode(',', $row['abogados_nombres']);
                    $userIds = [];
                    foreach ($nombres as $nombre) {
                        $uid = User::where('name', 'ilike', trim($nombre))->value('id');
                        if ($uid) $userIds[] = $uid;
                    }
                    if (!empty($userIds)) {
                        $caso->users()->sync($userIds);
                        // También actualizamos el user_id principal con el primero de la lista
                        $caso->update(['user_id' => $userIds[0]]);
                    }
                }
            }
        });

        return to_route('casos.index')->with('success', 'Importación completada con éxito.');
    }

    public function create(): Response
    {
        $this->authorize('create', Caso::class);
        $user = Auth::user();

        $cooperativas = ($user->tipo_usuario === 'admin')
            ? Cooperativa::select('id', 'nombre')->get()
            : $user->cooperativas()->select('id', 'nombre')->get();

        return Inertia::render('Casos/Create', [
            'cooperativas' => $cooperativas,
            'abogadosYGestores' => User::whereIn('tipo_usuario', ['abogado', 'gestor'])->select('id', 'name')->get(),
            'personas' => Persona::select('id', 'nombre_completo', 'numero_documento')->get(),
            'estructuraProcesal' => EspecialidadJuridica::with([
                'tiposProceso.subtipos.subprocesos' => fn($q) => $q->orderBy('nombre')
            ])->orderBy('nombre')->get(),
            'etapas_procesales' => DB::table('etapas_procesales')->orderBy('nombre')->pluck('nombre')->all(),
        ]);
    }

    public function togglePin(Caso $caso)
    {
        $this->authorize('update', $caso);
        $caso->update(['is_pinned' => !$caso->is_pinned]);
        
        return back()->with('success', $caso->is_pinned ? 'Caso fijado correctamente.' : 'Caso desfijado.');
    }

    public function verificarDuplicados(Request $request)
    {
        $radicado = trim($request->input('radicado', ''));
        $referencia = trim($request->input('referencia_credito', ''));
        $ignoreId = $request->input('ignore_id');

        if (empty($radicado) && empty($referencia)) {
            return response()->json([]);
        }

        $query = Caso::query();

        $query->where(function($q) use ($radicado, $referencia) {
            if ($radicado) {
                $cleanRad = preg_replace('/[^0-9]/', '', $radicado);
                $q->where('radicado', 'ilike', "%{$radicado}%");
                if ($cleanRad) {
                    $q->orWhereRaw("regexp_replace(radicado, '[^0-9]', '', 'g') = ?", [$cleanRad]);
                }
            }

            if ($referencia) {
                $q->orWhere('referencia_credito', 'ilike', $referencia);
            }
        });

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        $duplicates = $query->with(['deudor:id,nombre_completo', 'cooperativa:id,nombre'])
            ->limit(10)
            ->get()
            ->map(function ($caso) {
                return [
                    'id' => $caso->id,
                    'radicado' => $caso->radicado,
                    'referencia_credito' => $caso->referencia_credito,
                    'tipo' => $caso->tipo_proceso,
                    'fecha' => $caso->fecha_apertura ? $caso->fecha_apertura->format('Y-m-d') : null,
                    'cooperativa' => $caso->cooperativa?->nombre,
                    'deudor' => $caso->deudor?->nombre_completo,
                    'link' => route('casos.show', $caso->id)
                ];
            });

        return response()->json($duplicates);
    }

    public function updateChecklist(Request $request, Caso $caso)
    {
        $this->authorize('update', $caso);
        $caso->update(['checklist_seguimiento' => $request->input('checklist', [])]);
        return back()->with('success', 'Checklist actualizado.');
    }

    public function store(StoreCasoRequest $request): RedirectResponse
    {
        $this->authorize('create', Caso::class);
        $validated = $request->validated();

        $datosDeudor = $validated['deudor'];
        $datosCodeudores = $validated['codeudores'] ?? [];
        $userIds = $validated['user_id'];

        unset($validated['deudor'], $validated['codeudores'], $validated['user_id']);

        $caso = null;
        try {
            DB::transaction(function () use ($validated, $datosDeudor, $datosCodeudores, $userIds, $request, &$caso) {
                // 1. Manejo del Deudor Híbrido
                if (!empty($datosDeudor['is_new'])) {
                    if (empty($datosDeudor['numero_documento'])) {
                        throw new \Exception("El número de documento es obligatorio para crear un nuevo deudor.");
                    }
                    $deudor = Persona::withTrashed()->where('numero_documento', trim($datosDeudor['numero_documento']))->first();
                    if ($deudor) {
                        $deudor->update([
                            'nombre_completo' => trim($datosDeudor['nombre_completo']),
                            'tipo_documento' => $datosDeudor['tipo_documento'],
                            'dv' => $datosDeudor['dv'] ?? null,
                            'celular_1' => $datosDeudor['celular_1'] ?? null,
                            'correo_1' => $datosDeudor['correo_1'] ?? null,
                        ]);
                    } else {
                        $deudor = Persona::create([
                            'nombre_completo' => trim($datosDeudor['nombre_completo']),
                            'tipo_documento' => $datosDeudor['tipo_documento'],
                            'numero_documento' => trim($datosDeudor['numero_documento']),
                            'dv' => $datosDeudor['dv'] ?? null,
                            'celular_1' => $datosDeudor['celular_1'] ?? null,
                            'correo_1' => $datosDeudor['correo_1'] ?? null,
                        ]);
                    }
                    $validated['deudor_id'] = $deudor->id;
                } elseif (!empty($datosDeudor['id'])) {
                    // Si no es nuevo, simplemente nos aseguramos de que el ID sea el correcto
                    $validated['deudor_id'] = $datosDeudor['id'];
                }

                // 2. Crear Caso (usamos el primer abogado para la columna legacy user_id)
                $validated['user_id'] = $userIds[0] ?? null;
                $caso = Caso::create($validated);

                // 3. Sincronizar múltiples abogados
                $caso->users()->sync($userIds);

                // 4. Sincronizar Codeudores
                $this->sincronizarCodeudores($caso, $datosCodeudores);

                $caso->bitacoras()->create([
                    'user_id' => auth()->id(),
                    'accion' => $request->clonado_de_id ? 'Clonación de Caso' : 'Creación del Caso',
                    'comentario' => 'Caso registrado con asignación múltiple.',
                ]);

                // --- HUMANIZAR DETALLE NUEVO ---
                $detalleNuevo = $caso->getRawOriginal();
                foreach ($detalleNuevo as $key => $val) {
                    if (str_ends_with($key, '_id') && !empty($val)) {
                        try {
                            if ($key === 'user_id' || $key === 'abogado_id') {
                                $detalleNuevo[$key] = \App\Models\User::find($val)?->name ?? $val;
                            } elseif ($key === 'deudor_id') {
                                $detalleNuevo[$key] = \App\Models\Persona::find($val)?->nombre_completo ?? $val;
                            } elseif ($key === 'cooperativa_id') {
                                $detalleNuevo[$key] = \App\Models\Cooperativa::find($val)?->nombre ?? $val;
                            } elseif ($key === 'especialidad_id') {
                                $detalleNuevo[$key] = DB::table('especialidades_juridicas')->where('id', $val)->value('nombre') ?? $val;
                            } else {
                                $table = str_replace('_id', '', $key);
                                $tableName = match($table) {
                                    'tipo_proceso' => 'tipos_proceso',
                                    'subtipo_proceso' => 'subtipos_proceso',
                                    'subproceso' => 'subprocesos',
                                    'juzgado' => 'juzgados',
                                    default => $table . 's'
                                };
                                if (Schema::hasTable($tableName)) {
                                    $detalleNuevo[$key] = DB::table($tableName)->where('id', $val)->value('nombre') ?? $val;
                                }
                            }
                        } catch (\Exception $e) {}
                    }
                }

                AuditoriaEvento::create([
                    'user_id' => Auth::id(),
                    'evento' => 'CREAR_CASO',
                    'descripcion_breve' => "Caso #{$caso->id} creado para {$caso->deudor?->nombre_completo}",
                    'criticidad' => 'media',
                    'auditable_id' => $caso->id,
                    'auditable_type' => Caso::class,
                    'detalle_nuevo' => $detalleNuevo,
                    'direccion_ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            });
        } catch (\Exception $e) {
            Log::error("Error al crear caso: " . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'data' => $request->all()
            ]);
            return back()->withInput()->with('error', 'Ocurrió un error al registrar el caso: ' . $e->getMessage());
        }

        return to_route('casos.show', $caso->id)->with('success', '¡Caso registrado exitosamente!');
    }

    public function show(Caso $caso): Response
    {
        $this->authorize('view', $caso);
        
        // Registro automático de revisión diaria
        $this->registrarRevisionAutomatica($caso);

        $caso->load([
            'deudor:id,nombre_completo,numero_documento,tipo_documento,dv,celular_1,celular_2,correo_1,correo_2,addresses',
            'codeudores',
            'cooperativa',
            'user',
            'users',
            'documentos.persona:id,nombre_completo',
            'documentosGenerados.plantilla',
            'actuaciones' => fn($q) => $q->with('user:id,name')->orderBy('fecha_actuacion', 'desc'),
            'juzgado:id,nombre',
            'especialidad:id,nombre',
            'validacionesLegales',
        ]);

        $contratoActual = Contrato::where('caso_id', $caso->id)->where('estado', '!=', 'REESTRUCTURADO')->orderBy('id', 'desc')->first() 
                          ?? Contrato::where('caso_id', $caso->id)->orderBy('id', 'desc')->first();
        $caso->setRelation('contrato', $contratoActual);

        // Resumen Financiero
        $montoMostrar = (float) $caso->monto_total;
        $totalPagado = (float) $caso->monto_total_pagado;
        if ($contratoActual) {
            $valorContrato = DB::table('contrato_cuotas')->where('contrato_id', $contratoActual->id)->sum('valor') ?: (float) $contratoActual->monto;
            $cargos = DB::table('contrato_cargos')->where('contrato_id', $contratoActual->id)->sum('monto');
            $mora = DB::table('contrato_cuotas')->where('contrato_id', $contratoActual->id)->where('estado', '!=', 'PAGADA')->sum('intereses_mora_acumulados');
            $saldo = ($valorContrato + $cargos + $mora) - $totalPagado;
            $montoMostrar = $valorContrato;
        } else {
            $saldo = $montoMostrar - $totalPagado;
        }

        return Inertia::render('Casos/Show', [
            'caso' => $caso,
            'resumen_financiero' => ['monto_total' => $montoMostrar, 'total_pagado' => $totalPagado, 'saldo_pendiente' => max(0, $saldo), 'dias_mora' => $caso->dias_en_mora],
            'actuaciones' => $caso->actuaciones,
            'bitacoras' => Inertia::defer(fn() => $caso->bitacoras()->with('user')->latest()->get()),
            'auditoria' => Inertia::defer(fn() => $caso->auditoria()->with('usuario')->latest()->get()),
            'plantillas' => PlantillaDocumento::where('activa', true)->where(fn($q) => $q->where('cooperativa_id', $caso->cooperativa_id)->orWhereNull('cooperativa_id'))->orderBy('nombre')->get(['id', 'nombre', 'version']),
            'can' => ['update' => Auth::user()->can('update', $caso), 'delete' => Auth::user()->can('delete', $caso)],
        ]);
    }

    public function edit(Caso $caso): Response|RedirectResponse
    {
        $this->authorize('update', $caso);
        if ($caso->nota_cierre && Auth::user()->tipo_usuario !== 'admin') return to_route('casos.show', $caso->id)->with('error', 'El caso está cerrado.');

        $caso->load(['juzgado', 'cooperativa', 'user', 'users', 'deudor', 'codeudores']);
        $user = Auth::user();

        return Inertia::render('Casos/Edit', [
            'caso' => $caso,
            'cooperativas' => ($user->tipo_usuario === 'admin') ? Cooperativa::select('id', 'nombre')->get() : $user->cooperativas()->select('id', 'nombre')->get(),
            'abogadosYGestores' => User::whereIn('tipo_usuario', ['abogado', 'gestor'])->select('id', 'name')->get(),
            'personas' => Persona::select('id', 'nombre_completo', 'numero_documento')->get(),
            'estructuraProcesal' => EspecialidadJuridica::with(['tiposProceso.subtipos.subprocesos' => fn($q) => $q->orderBy('nombre')])->orderBy('nombre')->get(),
            'etapas_procesales' => DB::table('etapas_procesales')->orderBy('nombre')->pluck('nombre')->all(),
        ]);
    }

    public function update(UpdateCasoRequest $request, Caso $caso): RedirectResponse
    {
        $this->authorize('update', $caso);
        if ($caso->nota_cierre && Auth::user()->tipo_usuario !== 'admin') return to_route('casos.show', $caso->id)->with('error', 'El caso está cerrado.');

        $validated = $request->validated();
        $datosDeudor = $validated['deudor'];
        $datosCodeudores = $validated['codeudores'] ?? [];
        $userIds = $validated['user_id'];

        unset($validated['deudor'], $validated['codeudores'], $validated['user_id']);

        try {
            DB::transaction(function () use ($caso, $validated, $datosDeudor, $datosCodeudores, $userIds) {
                if (!empty($datosDeudor['is_new'])) {
                    if (empty($datosDeudor['numero_documento'])) {
                        throw new \Exception("El número de documento es obligatorio para crear/actualizar el deudor.");
                    }
                    $deudor = Persona::withTrashed()->where('numero_documento', trim($datosDeudor['numero_documento']))->first();
                    if ($deudor) {
                        $deudor->update([
                            'nombre_completo' => trim($datosDeudor['nombre_completo']),
                            'tipo_documento' => $datosDeudor['tipo_documento'],
                            'dv' => $datosDeudor['dv'] ?? null,
                            'celular_1' => $datosDeudor['celular_1'] ?? null,
                            'correo_1' => $datosDeudor['correo_1'] ?? null,
                        ]);
                    } else {
                        $deudor = Persona::create([
                            'nombre_completo' => trim($datosDeudor['nombre_completo']),
                            'tipo_documento' => $datosDeudor['tipo_documento'],
                            'numero_documento' => trim($datosDeudor['numero_documento']),
                            'dv' => $datosDeudor['dv'] ?? null,
                            'celular_1' => $datosDeudor['celular_1'] ?? null,
                            'correo_1' => $datosDeudor['correo_1'] ?? null,
                        ]);
                    }
                    $validated['deudor_id'] = $deudor->id;
                } elseif (!empty($datosDeudor['id'])) {
                    $validated['deudor_id'] = $datosDeudor['id'];
                }
                
                $original = $caso->getRawOriginal();
                $validated['user_id'] = $userIds[0] ?? null;
                $caso->update($validated);
                $changes = $caso->getChanges();

                if (!empty($changes)) {
                    $anterior = [];
                    $nuevo = [];
                    foreach ($changes as $key => $val) {
                        if (in_array($key, ['updated_at', 'ultima_actividad'])) continue;
                        
                        $oldVal = $original[$key] ?? null;
                        $newVal = $val;

                        // --- TRADUCCIÓN DE IDS A NOMBRES LEGIBLES ---
                        try {
                            if ($key === 'user_id' || $key === 'abogado_id') {
                                $oldVal = \App\Models\User::find($oldVal)?->name ?? $oldVal;
                                $newVal = \App\Models\User::find($newVal)?->name ?? $newVal;
                            } elseif ($key === 'deudor_id') {
                                $oldVal = \App\Models\Persona::find($oldVal)?->nombre_completo ?? $oldVal;
                                $newVal = \App\Models\Persona::find($newVal)?->nombre_completo ?? $newVal;
                            } elseif ($key === 'cooperativa_id') {
                                $oldVal = \App\Models\Cooperativa::find($oldVal)?->nombre ?? $oldVal;
                                $newVal = \App\Models\Cooperativa::find($newVal)?->nombre ?? $newVal;
                            } elseif ($key === 'especialidad_id') {
                                $oldVal = DB::table('especialidades_juridicas')->where('id', $oldVal)->value('nombre') ?? $oldVal;
                                $newVal = DB::table('especialidades_juridicas')->where('id', $newVal)->value('nombre') ?? $newVal;
                            } elseif (str_ends_with($key, '_id')) {
                                // Intento genérico para otros IDs (Tipo Proceso, Subproceso, etc.)
                                $table = str_replace('_id', '', $key);
                                // Pluralización básica o mapeo manual si es necesario
                                $tableName = match($table) {
                                    'tipo_proceso' => 'tipos_proceso',
                                    'subtipo_proceso' => 'subtipos_proceso',
                                    'subproceso' => 'subprocesos',
                                    'juzgado' => 'juzgados',
                                    default => $table . 's'
                                };
                                if (Schema::hasTable($tableName)) {
                                    $oldVal = DB::table($tableName)->where('id', $oldVal)->value('nombre') ?? $oldVal;
                                    $newVal = DB::table($tableName)->where('id', $newVal)->value('nombre') ?? $newVal;
                                }
                            }
                        } catch (\Exception $e) {
                            // Si falla la traducción, dejamos el ID original
                        }

                        $anterior[$key] = $oldVal;
                        $nuevo[$key] = $newVal;
                    }

                    if (!empty($nuevo)) {
                        AuditoriaEvento::create([
                            'user_id' => Auth::id(),
                            'evento' => 'ACTUALIZAR_CASO',
                            'descripcion_breve' => "Actualización de datos del Caso #{$caso->id}",
                            'auditable_id' => $caso->id,
                            'auditable_type' => Caso::class,
                            'criticidad' => 'baja',
                            'detalle_anterior' => $anterior,
                            'detalle_nuevo' => $nuevo,
                            'direccion_ip' => request()->ip(),
                            'user_agent' => request()->userAgent(),
                        ]);
                    }
                }

                $caso->users()->sync($userIds);
                $this->sincronizarCodeudores($caso, $datosCodeudores);
                $caso->bitacoras()->create(['user_id' => auth()->id(), 'accion' => 'Actualización de Caso', 'comentario' => 'Se actualizaron los datos y responsables.']);
            });
        } catch (\Exception $e) {
            Log::error("Error al actualizar caso: " . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'caso_id' => $caso->id,
                'data' => $request->all()
            ]);
            return back()->withInput()->with('error', 'Ocurrió un error al actualizar el caso: ' . $e->getMessage());
        }

        return to_route('casos.show', $caso->id)->with('success', '¡Caso actualizado exitosamente!');
    }

    public function destroy(Caso $caso): RedirectResponse
    {
        if (Auth::user()->tipo_usuario !== 'admin') return back()->with('error', 'Solo administradores.');

        $id = $caso->id;
        $deudor = $caso->deudor?->nombre_completo ?? 'N/A';
        
        AuditoriaEvento::create([
            'user_id' => Auth::id(),
            'evento' => 'SUSPENDER_CASO',
            'descripcion_breve' => "Caso #{$id} suspendido: Deudor {$deudor}",
            'auditable_id' => $id,
            'auditable_type' => Caso::class,
            'criticidad' => 'alta',
            'direccion_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        $caso->delete();
        return to_route('casos.index')->with('success', '¡Caso movido a la papelera!');
    }

    public function close(Request $request, Caso $caso): RedirectResponse
    {
        $this->authorize('update', $caso);
        $validated = $request->validate(['nota_cierre' => ['required', 'string', 'max:2000']]);
        $caso->update(['nota_cierre' => $validated['nota_cierre']]);
        $caso->bitacoras()->create(['user_id' => auth()->id(), 'accion' => 'Cierre de Caso', 'comentario' => $validated['nota_cierre']]);
        return to_route('casos.edit', $caso->id)->with('success', '¡Caso cerrado exitosamente!');
    }

    public function reopen(Request $request, Caso $caso): RedirectResponse
    {
        if (Auth::user()->tipo_usuario !== 'admin') return to_route('casos.edit', $caso->id)->with('error', 'No autorizado.');
        $caso->update(['nota_cierre' => null]);
        $caso->bitacoras()->create(['user_id' => auth()->id(), 'accion' => 'Reapertura de Caso', 'comentario' => 'Reabierto por admin.']);
        return to_route('casos.edit', $caso->id)->with('success', '¡Caso reabierto exitosamente!');
    }

    public function storeActuacion(Request $request, Caso $caso)
    {
        $validated = $request->validate(['nota' => ['required', 'string'], 'fecha_actuacion' => ['required', 'date']]);
        $caso->actuaciones()->create(['nota' => $validated['nota'], 'fecha_actuacion' => $validated['fecha_actuacion'], 'user_id' => Auth::id()]);
        return back()->with('success', 'Actuación registrada.');
    }

    public function updateActuacion(Request $request, Actuacion $actuacion)
    {
        $validated = $request->validate(['nota' => ['required', 'string'], 'fecha_actuacion' => ['required', 'date']]);
        $actuacion->update($validated);
        return back(303)->with('success', 'Actuación actualizada.');
    }

    public function destroyActuacion(Actuacion $actuacion) { $actuacion->delete(); return back(303)->with('success', 'Actuación eliminada.'); }

    public function unlock(Request $request, Caso $caso): RedirectResponse { $caso->update(['bloqueado' => false, 'motivo_bloqueo' => null]); return to_route('casos.show', $caso->id)->with('success', 'Desbloqueado.'); }

    public function clonar(Caso $caso): Response
    {
        $this->authorize('create', Caso::class);
        $caso->load('juzgado', 'codeudores', 'cooperativa', 'user', 'deudor');
        return Inertia::render('Casos/Create', [
            'casoAClonar' => $caso,
            'cooperativas' => Cooperativa::all(['id', 'nombre']),
            'abogadosYGestores' => User::whereIn('tipo_usuario', ['abogado', 'gestor'])->select('id', 'name')->get(),
            'personas' => Persona::select('id', 'nombre_completo', 'numero_documento')->get(),
            'estructuraProcesal' => EspecialidadJuridica::with(['tiposProceso.subtipos.subprocesos' => fn($q) => $q->orderBy('nombre')])->orderBy('nombre')->get(),
            'etapas_procesales' => DB::table('etapas_procesales')->orderBy('nombre')->pluck('nombre')->all(),
        ]);
    }

    private function sincronizarCodeudores(Caso $caso, array $datosCodeudores): void
    {
        $ids = [];
        foreach ($datosCodeudores as $d) {
            $numDoc = trim($d['numero_documento'] ?? '');
            if (empty($numDoc)) continue;

            $c = Codeudor::updateOrCreate(['numero_documento' => $numDoc], [
                'nombre_completo' => trim($d['nombre_completo'] ?? 'SIN NOMBRE'),
                'tipo_documento' => $d['tipo_documento'] ?? 'CC',
                'dv' => $d['dv'] ?? null,
                'celular' => $d['celular'] ?? null,
                'correo' => $d['correo'] ?? null,
                'addresses' => $d['addresses'] ?? null,
                'social_links' => $d['social_links'] ?? null,
            ]);
            $ids[] = $c->id;
        }
        $caso->codeudores()->sync($ids);
    }
}
