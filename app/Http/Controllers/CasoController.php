<?php

namespace App\Http\Controllers;

use App\Models\Caso;
use App\Models\Cooperativa;
use App\Models\Persona;
use App\Models\Codeudor;
use App\Models\User;
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

        $query = Caso::with(['cooperativa', 'deudor', 'user', 'users']);

        // --- 1. Filtros por Rol de Usuario (Seguridad) ---
        if ($user->tipo_usuario === 'admin') {
            // Admin ve todo
        } elseif (in_array($user->tipo_usuario, ['gestor', 'abogado'])) {
            $query->where(function($q) use ($user) {
                // Opción A: Casos de sus cooperativas asignadas
                if (method_exists($user, 'cooperativas')) {
                    $cooperativaIds = $user->cooperativas->pluck('id');
                    $q->whereIn('cooperativa_id', $cooperativaIds);
                }
                // Opción B: Casos donde es responsable directo (Nueva tabla pivote)
                $q->orWhereHas('users', function($uq) use ($user) {
                    $uq->where('users.id', $user->id);
                });
                // Opción C: Casos donde es el responsable legacy (por si acaso)
                $q->orWhere('user_id', $user->id);
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

        // --- 3. Filtro de Búsqueda General ---
        $query->when($request->input('search'), function ($q, $search) {
            $q->where(function ($subq) use ($search) {
                $subq->where('tipo_proceso', 'ilike', "%{$search}%")
                    ->orWhere('etapa_actual', 'ilike', "%{$search}%")
                    ->orWhere('referencia_credito', 'ilike', "%{$search}%")
                    ->orWhere('radicado', 'ilike', "%{$search}%")
                    ->orWhereHas('deudor', function ($deudorQuery) use ($search) {
                        $deudorQuery->where('nombre_completo', 'ilike', "%{$search}%")
                            ->orWhere('numero_documento', 'ilike', "%{$search}%");
                    });
            });
        });

        $casos = $query->orderBy('updated_at', 'desc')->paginate(20)->withQueryString();

        $abogados = [];
        if (in_array($user->tipo_usuario, ['admin', 'gestor', 'abogado'])) {
            $abogados = User::whereIn('tipo_usuario', ['abogado', 'gestor'])->select('id', 'name')->orderBy('name')->get();
        }

        return Inertia::render('Casos/Index', [
            'casos' => $casos,
            'abogados' => $abogados,
            'filters' => $request->only(['search', 'abogado_id']),
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

    public function store(StoreCasoRequest $request): RedirectResponse
    {
        $this->authorize('create', Caso::class);
        $validated = $request->validated();

        $datosDeudor = $validated['deudor'];
        $datosCodeudores = $validated['codeudores'] ?? [];
        $userIds = $validated['user_id'];

        unset($validated['deudor'], $validated['codeudores'], $validated['user_id']);

        $caso = null;
        DB::transaction(function () use ($validated, $datosDeudor, $datosCodeudores, $userIds, $request, &$caso) {
            // 1. Manejo del Deudor Híbrido
            if ($datosDeudor['is_new']) {
                $deudor = Persona::create([
                    'nombre_completo' => $datosDeudor['nombre_completo'],
                    'tipo_documento' => $datosDeudor['tipo_documento'],
                    'numero_documento' => $datosDeudor['numero_documento'],
                    'celular_1' => $datosDeudor['celular_1'] ?? null,
                    'correo_1' => $datosDeudor['correo_1'] ?? null,
                ]);
                if (!empty($datosDeudor['cooperativas_ids'])) $deudor->cooperativas()->sync($datosDeudor['cooperativas_ids']);
                if (!empty($datosDeudor['abogados_ids'])) $deudor->abogados()->sync($datosDeudor['abogados_ids']);
                $validated['deudor_id'] = $deudor->id;
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

            AuditoriaEvento::create([
                'user_id' => Auth::id(),
                'evento' => 'CREAR_CASO',
                'descripcion_breve' => "Caso #{$caso->id} creado para {$caso->deudor?->nombre_completo}",
                'criticidad' => 'media',
                'auditable_id' => $caso->id,
                'auditable_type' => Caso::class,
                'detalle_nuevo' => $caso->getRawOriginal(),
                'direccion_ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        });

        return to_route('casos.show', $caso->id)->with('success', '¡Caso registrado exitosamente!');
    }

    public function show(Caso $caso): Response
    {
        $this->authorize('view', $caso);
        
        // Registro automático de revisión diaria
        $this->registrarRevisionAutomatica($caso);

        $caso->load([
            'deudor:id,nombre_completo,numero_documento,tipo_documento,celular_1,celular_2,correo_1,correo_2,addresses',
            'codeudores',
            'cooperativa',
            'user',
            'users',
            'documentos.persona:id,nombre_completo',
            'documentosGenerados.plantilla',
            'bitacoras.user',
            'auditoria.usuario',
            'actuaciones' => fn($q) => $q->with('user:id,name')->orderBy('fecha_actuacion', 'desc'),
            'juzgado:id,nombre',
            'especialidad:id,nombre',
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

        DB::transaction(function () use ($caso, $validated, $datosDeudor, $datosCodeudores, $userIds) {
            if ($datosDeudor['is_new']) {
                $deudor = Persona::create(['nombre_completo' => $datosDeudor['nombre_completo'], 'tipo_documento' => $datosDeudor['tipo_documento'] ?? 'CC', 'numero_documento' => $datosDeudor['numero_documento']]);
                $validated['deudor_id'] = $deudor->id;
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
                    $anterior[$key] = $original[$key] ?? null;
                    $nuevo[$key] = $val;
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

        return to_route('casos.show', $caso->id)->with('success', '¡Caso actualizado exitosamente!');
    }

    public function destroy(Caso $caso): RedirectResponse
    {
        if (Auth::user()->tipo_usuario !== 'admin') return back()->with('error', 'Solo administradores.');
        $caso->delete();
        return to_route('casos.index')->with('success', '¡Caso eliminado exitosamente!');
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
            $c = Codeudor::updateOrCreate(['numero_documento' => $d['numero_documento']], [
                'nombre_completo' => $d['nombre_completo'], 'tipo_documento' => $d['tipo_documento'] ?? 'CC',
                'celular' => $d['celular'] ?? null, 'correo' => $d['correo'] ?? null,
                'addresses' => $d['addresses'] ?? null, 'social_links' => $d['social_links'] ?? null,
            ]);
            $ids[] = $c->id;
        }
        $caso->codeudores()->sync($ids);
    }
}
