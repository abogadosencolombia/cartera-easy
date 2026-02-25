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

class CasoController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Caso::class);
        $user = Auth::user();

        $query = Caso::with(['cooperativa', 'deudor', 'user']);

        // --- 1. Filtros por Rol de Usuario ---
        if ($user->tipo_usuario === 'admin') {
            // Admin ve todo
        } elseif (in_array($user->tipo_usuario, ['gestor', 'abogado'])) {
            if (method_exists($user, 'cooperativas')) {
                $cooperativaIds = $user->cooperativas->pluck('id');
                $query->whereIn('cooperativa_id', $cooperativaIds);
            }
        } elseif ($user->tipo_usuario === 'cli') {
            $query->where(function ($q) use ($user) {
                $q->where('deudor_id', $user->persona_id);
            });
        }

        // --- 2. Filtro por Abogado/Gestor ---
        $query->when($request->input('abogado_id'), function ($q, $abogadoId) {
            $q->where('user_id', $abogadoId);
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

        // --- 4. Lista de Abogados para el Select ---
        $abogados = [];
        if (in_array($user->tipo_usuario, ['admin', 'gestor', 'abogado'])) {
            $abogados = User::whereIn('tipo_usuario', ['abogado', 'gestor'])
                ->select('id', 'name')
                ->orderBy('name')
                ->get();
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

        $abogadosYGestores = User::whereIn('tipo_usuario', ['abogado', 'gestor'])->select('id', 'name')->get();
        $personas = Persona::select('id', 'nombre_completo', 'numero_documento')->get();

        return Inertia::render('Casos/Create', [
            'cooperativas' => $cooperativas,
            'abogadosYGestores' => $abogadosYGestores,
            'personas' => $personas,
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

        if ($request->has('link_drive')) {
            $validated['link_drive'] = $request->input('link_drive');
        }

        $datosCodeudores = $validated['codeudores'] ?? [];
        unset($validated['codeudores']);

        $caso = null;
        DB::transaction(function () use ($validated, $datosCodeudores, $request, &$caso) {
            $caso = Caso::create($validated);
            $this->sincronizarCodeudores($caso, $datosCodeudores);

            $caso->bitacoras()->create([
                'user_id' => auth()->id(),
                'accion' => $request->clonado_de_id ? 'Clonación de Caso' : 'Creación del Caso',
                'comentario' => 'Caso registrado en el sistema.',
            ]);

            AuditoriaEvento::create([
                'user_id' => Auth::id(),
                'evento' => 'CREAR_CASO',
                'descripcion_breve' => "Se ha creado el caso #{$caso->id} para el deudor {$caso->deudor?->nombre_completo}",
                'criticidad' => 'media',
                'direccion_ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        });

        return to_route('casos.show', $caso->id)->with('success', '¡Caso registrado exitosamente!');
    }

    public function show(Caso $caso): Response
    {
        $this->authorize('view', $caso);

        // CORRECCIÓN SQL: Se ajustaron los nombres de columnas a los reales de la tabla 'personas'
        // Antes: ...celular,email,direccion,ciudad
        // Ahora: ...celular_1,celular_2,correo_1,correo_2,addresses
        $caso->load([
            'deudor:id,nombre_completo,numero_documento,tipo_documento,celular_1,celular_2,correo_1,correo_2,addresses',
            'codeudores',
            'cooperativa',
            'user',
            'documentos.persona:id,nombre_completo',
            'documentosGenerados.plantilla',
            'bitacoras.user',
            'actuaciones' => function ($query) {
                $query->with('user:id,name')->orderBy('fecha_actuacion', 'desc');
            },
            'juzgado:id,nombre',
            'especialidad:id,nombre',
        ]);

        $contratoActual = Contrato::where('caso_id', $caso->id)
            ->where('estado', '!=', 'REESTRUCTURADO')
            ->orderBy('id', 'desc')
            ->first();

        if (!$contratoActual) {
            $contratoActual = Contrato::where('caso_id', $caso->id)->orderBy('id', 'desc')->first();
        }
        $caso->setRelation('contrato', $contratoActual);

        // --- CÁLCULO FINANCIERO ---
        $montoMostrar = (float) $caso->monto_total;
        $totalPagado = (float) $caso->monto_total_pagado;
        $diasMoraVisual = $caso->dias_en_mora;

        if ($contratoActual) {
            $valorContrato = DB::table('contrato_cuotas')->where('contrato_id', $contratoActual->id)->sum('valor');
            if ($valorContrato == 0 && isset($contratoActual->monto)) {
                $valorContrato = (float) $contratoActual->monto;
            }
            $montoMostrar = $valorContrato;

            $cargosEnContrato = DB::table('contrato_cargos')->where('contrato_id', $contratoActual->id)->sum('monto');
            $moraEnContrato = DB::table('contrato_cuotas')
                ->where('contrato_id', $contratoActual->id)
                ->where('estado', '!=', 'PAGADA')
                ->sum('intereses_mora_acumulados');
            $saldoPendiente = ($valorContrato + $cargosEnContrato + $moraEnContrato) - $totalPagado;
        } else {
            $saldoPendiente = ($montoMostrar - $totalPagado);
        }

        $resumenFinanciero = [
            'monto_total' => $montoMostrar,
            'total_pagado' => $totalPagado,
            'saldo_pendiente' => max(0, $saldoPendiente),
            'dias_mora' => $diasMoraVisual,
        ];

        $caso->setRelation('auditoria', $caso->bitacoras);

        // Motor de Cumplimiento Legal
        $tipoProceso = TipoProceso::where('nombre', 'Ilike', trim($caso->tipo_proceso))->first();
        $requisitosAplicables = collect();
        if ($tipoProceso) {
            $requisitosAplicables = RequisitoDocumento::where('tipo_proceso_id', $tipoProceso->id)
                ->where(function ($q) use ($caso) {
                    $q->whereNull('cooperativa_id')
                        ->orWhere('cooperativa_id', $caso->cooperativa_id);
                })
                ->get();
        }

        $documentosSubidos = $caso->documentos->pluck('tipo_documento')
            ->map(fn($doc) => strtolower(trim($doc)))
            ->toArray();

        $cumplimientoLegal = $requisitosAplicables->map(function ($req) use ($documentosSubidos) {
            $nombreDocRequerido = strtolower(trim($req->tipo_documento_requerido));
            $cumple = in_array($nombreDocRequerido, $documentosSubidos);

            return [
                'id' => $req->id,
                'validacion' => ucfirst($req->tipo_documento_requerido),
                'estado' => $cumple ? 'CUMPLE' : 'FALTANTE',
                'riesgo' => $cumple ? 'BAJO' : 'ALTO',
                'observacion' => $cumple
                    ? 'El documento ha sido cargado correctamente.'
                    : 'Este documento es obligatorio para el proceso y no se encuentra en el expediente.',
                'accion_correctiva' => $cumple ? null : 'Subir Documento',
            ];
        });

        $plantillasDisponibles = PlantillaDocumento::where('activa', true)
            ->where(function ($query) use ($caso) {
                $query->where('cooperativa_id', $caso->cooperativa_id)
                    ->orWhereNull('cooperativa_id');
            })
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'version']);

        return Inertia::render('Casos/Show', [
            'caso' => $caso,
            'resumen_financiero' => $resumenFinanciero,
            'cumplimiento_legal' => $cumplimientoLegal,
            'actuaciones' => $caso->actuaciones,
            'plantillas' => $plantillasDisponibles,
            'can' => [
                'update' => Auth::user()->can('update', $caso),
                'delete' => Auth::user()->can('delete', $caso),
            ],
        ]);
    }

    public function edit(Caso $caso): Response|RedirectResponse
    {
        $this->authorize('update', $caso);
        $user = Auth::user();

        // --- 🔒 CANDADO DE SEGURIDAD PARA CASOS CERRADOS ---
        if ($caso->nota_cierre && $user->tipo_usuario !== 'admin') {
            return to_route('casos.show', $caso->id)
                ->with('error', 'El caso está cerrado. Solo los administradores pueden editarlo o reabrirlo.');
        }

        $caso->load(['juzgado', 'cooperativa', 'user', 'deudor', 'codeudores']);

        $cooperativas = ($user->tipo_usuario === 'admin')
            ? Cooperativa::select('id', 'nombre')->get()
            : $user->cooperativas()->select('id', 'nombre')->get();

        $abogadosYGestores = User::whereIn('tipo_usuario', ['abogado', 'gestor'])->select('id', 'name')->get();
        $personas = Persona::select('id', 'nombre_completo', 'numero_documento')->get();

        return Inertia::render('Casos/Edit', [
            'caso' => $caso,
            'cooperativas' => $cooperativas,
            'abogadosYGestores' => $abogadosYGestores,
            'personas' => $personas,
            'estructuraProcesal' => EspecialidadJuridica::with([
                'tiposProceso.subtipos.subprocesos' => fn($q) => $q->orderBy('nombre')
            ])->orderBy('nombre')->get(),
            'etapas_procesales' => DB::table('etapas_procesales')->orderBy('nombre')->pluck('nombre')->all(),
        ]);
    }

    public function update(UpdateCasoRequest $request, Caso $caso): RedirectResponse
    {
        $this->authorize('update', $caso);

        // --- 🔒 CANDADO DE SEGURIDAD EN EL UPDATE ---
        if ($caso->nota_cierre && Auth::user()->tipo_usuario !== 'admin') {
            return to_route('casos.show', $caso->id)->with('error', 'Acción denegada: El caso está cerrado.');
        }

        $validated = $request->validated();

        if ($request->has('link_drive')) {
            $validated['link_drive'] = $request->input('link_drive');
        }

        $datosCodeudores = $validated['codeudores'] ?? [];
        unset($validated['codeudores']);

        DB::transaction(function () use ($caso, $validated, $datosCodeudores) {
            $caso->update($validated);
            $this->sincronizarCodeudores($caso, $datosCodeudores);

            $caso->bitacoras()->create([
                'user_id' => auth()->id(),
                'accion' => 'Actualización de Caso',
                'comentario' => 'Se actualizaron los datos principales del caso.',
            ]);

            AuditoriaEvento::create([
                'user_id' => Auth::id(),
                'evento' => 'EDITAR_CASO',
                'descripcion_breve' => "Se actualizó el caso #{$caso->id} (Rad: {$caso->radicado})",
                'criticidad' => 'baja',
                'direccion_ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        });

        return to_route('casos.show', $caso->id)->with('success', '¡Caso actualizado exitosamente!');
    }

    public function destroy(Caso $caso): RedirectResponse
    {
        if (Auth::user()->tipo_usuario !== 'admin') {
            AuditoriaEvento::create([
                'user_id' => Auth::id(),
                'evento' => 'INTENTO_ELIMINAR_CASO',
                'descripcion_breve' => "Usuario no autorizado intentó eliminar el caso #{$caso->id}",
                'criticidad' => 'alta',
                'direccion_ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
            return back()->with('error', 'Acción no autorizada. Solo los administradores pueden eliminar casos.');
        }

        AuditoriaEvento::create([
            'user_id' => Auth::id(),
            'evento' => 'ELIMINAR_CASO',
            'descripcion_breve' => "El administrador eliminó permanentemente el caso #{$caso->id} (Rad: {$caso->radicado})",
            'criticidad' => 'alta',
            'direccion_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        $caso->delete();
        return to_route('casos.index')->with('success', '¡Caso eliminado exitosamente!');
    }

    public function close(Request $request, Caso $caso): RedirectResponse
    {
        $this->authorize('update', $caso);
        $validated = $request->validate(['nota_cierre' => ['required', 'string', 'max:2000']]);

        $caso->update(['nota_cierre' => $validated['nota_cierre']]);

        $caso->bitacoras()->create([
            'user_id' => auth()->id(),
            'accion' => 'Cierre de Caso',
            'comentario' => 'Caso cerrado. Motivo: ' . $validated['nota_cierre'],
        ]);

        AuditoriaEvento::create([
            'user_id' => Auth::id(),
            'evento' => 'CERRAR_CASO',
            'descripcion_breve' => "Caso #{$caso->id} cerrado. Nota: {$validated['nota_cierre']}",
            'criticidad' => 'media',
            'direccion_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return to_route('casos.edit', $caso->id)->with('success', '¡Caso cerrado exitosamente!');
    }

    public function reopen(Request $request, Caso $caso): RedirectResponse
    {
        if (Auth::user()->tipo_usuario !== 'admin') {
            return to_route('casos.edit', $caso->id)->with('error', 'No tienes permiso para reabrir este caso.');
        }

        $caso->update(['nota_cierre' => null]);

        $caso->bitacoras()->create([
            'user_id' => auth()->id(),
            'accion' => 'Reapertura de Caso',
            'comentario' => 'El caso ha sido reabierto por ' . Auth::user()->name,
        ]);

        AuditoriaEvento::create([
            'user_id' => Auth::id(),
            'evento' => 'REABRIR_CASO',
            'descripcion_breve' => "Caso #{$caso->id} reabierto por administrador.",
            'criticidad' => 'media',
            'direccion_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return to_route('casos.edit', $caso->id)->with('success', '¡Caso reabierto exitosamente!');
    }

    public function storeActuacion(Request $request, Caso $caso)
    {
        $validated = $request->validate([
            'nota' => ['required', 'string', 'max:5000'],
            'fecha_actuacion' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $caso->actuaciones()->create([
            'nota' => $validated['nota'],
            'fecha_actuacion' => $validated['fecha_actuacion'],
            'user_id' => Auth::id(),
        ]);

        return back()->with('success', 'Actuación registrada.');
    }

    public function updateActuacion(Request $request, Actuacion $actuacion)
    {
        $user = Auth::user();
        if (!$user || !in_array($user->tipo_usuario, ['admin', 'gestor', 'abogado'])) {
            abort(403, 'No autorizado para editar esta actuación.');
        }

        $validated = $request->validate([
            'nota' => ['required', 'string', 'max:5000'],
            'fecha_actuacion' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $actuacion->update($validated);
        return back(303)->with('success', 'Actuación actualizada.');
    }

    public function destroyActuacion(Actuacion $actuacion)
    {
        $user = Auth::user();
        if (!$user || !in_array($user->tipo_usuario, ['admin', 'gestor', 'abogado'])) {
            abort(403, 'No autorizado para eliminar esta actuación.');
        }

        $actuacion->delete();
        return back(303)->with('success', 'Actuación eliminada.');
    }

    public function unlock(Request $request, Caso $caso): RedirectResponse
    {
        $user = Auth::user();
        if (!in_array($user->tipo_usuario, ['admin', 'abogado', 'gestor'])) {
            return to_route('casos.show', $caso->id)->with('error', 'No tienes permiso para desbloquear este caso.');
        }

        $caso->update(['bloqueado' => false, 'motivo_bloqueo' => null]);
        return to_route('casos.show', $caso->id)->with('success', '¡Caso desbloqueado exitosamente!');
    }

    public function clonar(Caso $caso): Response
    {
        $this->authorize('create', Caso::class);
        $this->authorize('view', $caso);
        $caso->load('juzgado', 'codeudores', 'cooperativa', 'user', 'deudor');

        return Inertia::render('Casos/Create', [
            'casoAClonar' => $caso,
            'cooperativas' => Cooperativa::all(['id', 'nombre']),
            'abogadosYGestores' => User::whereIn('tipo_usuario', ['abogado', 'gestor'])->select('id', 'name')->get(),
            'personas' => Persona::select('id', 'nombre_completo', 'numero_documento')->get(),
            'estructuraProcesal' => EspecialidadJuridica::with([
                'tiposProceso.subtipos.subprocesos' => fn($q) => $q->orderBy('nombre')
            ])->orderBy('nombre')->get(),
            'etapas_procesales' => DB::table('etapas_procesales')->orderBy('nombre')->pluck('nombre')->all(),
        ]);
    }

    private function sincronizarCodeudores(Caso $caso, array $datosCodeudores): void
    {
        $idsCodeudores = [];
        foreach ($datosCodeudores as $datosPersona) {
            $codeudor = Codeudor::updateOrCreate(
                ['numero_documento' => $datosPersona['numero_documento']],
                [
                    'nombre_completo' => $datosPersona['nombre_completo'],
                    'tipo_documento' => $datosPersona['tipo_documento'] ?? 'CC',
                    'celular' => $datosPersona['celular'] ?? null,
                    'correo' => $datosPersona['correo'] ?? null,
                    'addresses' => $datosPersona['addresses'] ?? null,
                    'social_links' => $datosPersona['social_links'] ?? null,
                ]
            );
            $idsCodeudores[] = $codeudor->id;
        }
        $caso->codeudores()->sync($idsCodeudores);
    }
}