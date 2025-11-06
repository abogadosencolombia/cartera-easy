<?php

namespace App\Http\Controllers;

use App\Models\Caso;
use App\Models\Cooperativa;
use App\Models\Persona;
// --- INICIO: AÑADIDOS ---
use App\Models\Codeudor; // Asumo que está en App\Models
// --- FIN: AÑADIDOS ---
use App\Models\User;
use App\Models\PlantillaDocumento;
// use App\Models\TipoProceso; // <-- Comentado, ya no se usa directamente aquí
use App\Models\EspecialidadJuridica; // <-- AÑADIDO
use App\Http\Requests\StoreCasoRequest;
use App\Http\Requests\UpdateCasoRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB; // <--- ASEGÚRATE QUE ESTÉ IMPORTADO
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

// --- INICIO: Añadidos para Actuaciones ---
use App\Models\Actuacion;
// --- FIN: Añadidos para Actuaciones ---

class CasoController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Caso::class);
        $user = Auth::user();

        $query = Caso::with(['cooperativa', 'deudor', 'user']);

        // Visibilidad por rol
        if ($user->tipo_usuario === 'admin') {
            // Admin ve todo
        } elseif (in_array($user->tipo_usuario, ['gestor', 'abogado'])) {
            $cooperativaIds = $user->cooperativas->pluck('id');
            $query->whereIn('cooperativa_id', $cooperativaIds);
        } elseif ($user->tipo_usuario === 'cli') {
            $query->where(function ($q) use ($user) {
                $q->where('deudor_id', $user->persona_id);
                // --- INICIO: CAMBIO ---
                // Las búsquedas 'codeudor1_id' y 'codeudor2_id' se eliminan
                // ya que los codeudores ahora están en una tabla separada
                // y no están vinculados directamente a un 'user' tipo 'cli'.
                // --- FIN: CAMBIO ---
            });
        }

        // Búsqueda unificada
        $query->when($request->input('search'), function ($q, $search) {
            $q->where(function ($subq) use ($search) {
                $subq->where('tipo_proceso', 'ilike', "%{$search}%")
                    ->orWhere('etapa_actual', 'ilike', "%{$search}%")
                    ->orWhere('referencia_credito', 'ilike', "%{$search}%") // Nro. Proceso en UI (CORREGIDO)
                    
                    // --- INICIO: CAMBIO (BUSCADOR MEJORADO) ---
                    ->orWhere('radicado', 'ilike', "%{$search}%")
                    ->orWhere('estado_proceso', 'ilike', "%{$search}%") // Buscar por estado
                    ->orWhere('subtipo_proceso', 'ilike', "%{$search}%") // Buscar por proceso (subtipo)
                    // --- FIN: CAMBIO (BUSCADOR MEJORADO) ---

                    ->orWhereHas('deudor', function ($deudorQuery) use ($search) {
                        $deudorQuery->where('nombre_completo', 'ilike', "%{$search}%")
                                    ->orWhere('numero_documento', 'ilike', "%{$search}%");
                    })
                    ->orWhereHas('cooperativa', function ($coopQuery) use ($search) {
                        $coopQuery->where('nombre', 'ilike', "%{$search}%"); // CORREGIDO
                    })
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'ilike', "%{$search}%"); // CORREGIDO
                    })
                    // --- INICIO: CAMBIO (BUSCADOR MEJORADO) ---
                    ->orWhereHas('juzgado', function ($juzgadoQuery) use ($search) {
                        $juzgadoQuery->where('nombre', 'ilike', "%{$search}%"); // Buscar por nombre de juzgado
                    });
                    // --- FIN: CAMBIO (BUSCADOR MEJORADO) ---
            });
        });

        $casos = $query->latest()->paginate(20)->withQueryString();

        return Inertia::render('Casos/Index', [
            'casos'   => $casos,
            'filters' => $request->only(['search']),
            'can'     => ['delete_cases' => $user->can('delete', Caso::class)],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Caso::class);
        $user = Auth::user();

        $cooperativas = ($user->tipo_usuario === 'admin')
            ? Cooperativa::select('id', 'nombre')->get()
            : $user->cooperativas()->select('id', 'nombre')->get();

        $abogadosYGestores = User::whereIn('tipo_usuario', ['abogado', 'gestor'])
            ->select('id', 'name')
            ->get();

        $personas = Persona::select('id', 'nombre_completo', 'numero_documento')->get();

        return Inertia::render('Casos/Create', [
            'cooperativas'        => $cooperativas,
            'abogadosYGestores'   => $abogadosYGestores,
            'personas'            => $personas,
            
            // =================================================================
            // ===== ¡CAMBIO IMPORTANTE AQUÍ! (Carga de 4 NIVELES) =========
            // =================================================================
            // 'estructuraProcesal'  => EspecialidadJuridica::with('tiposProceso.subtipos')->orderBy('nombre')->get(), // <-- LÍNEA ANTIGUA (3 NIVELES)
            'estructuraProcesal'  => EspecialidadJuridica::with([
                                        'tiposProceso' => fn($q) => $q->orderBy('nombre'),
                                        'tiposProceso.subtipos' => fn($q) => $q->orderBy('nombre'),
                                        'tiposProceso.subtipos.subprocesos' => fn($q) => $q->orderBy('nombre') // <-- LÍNEA NUEVA (4 NIVELES)
                                    ])->orderBy('nombre')->get(),
            // =================================================================
            // =================== FIN DEL CAMBIO ==============================
            // =================================================================

            'etapas_procesales'   => DB::table('etapas_procesales')->orderBy('nombre')->pluck('nombre')->all(),
        ]);
    }

    public function store(StoreCasoRequest $request): RedirectResponse
    {
        $this->authorize('create', Caso::class);

        // --- INICIO: CAMBIO (Lógica de Codeudores) ---
        $validated = $request->validated();
        
        // 1. Separar los datos de los codeudores
        $datosCodeudores = $validated['codeudores'] ?? [];
        unset($validated['codeudores']);

        $caso = null;
        DB::transaction(function () use ($validated, $datosCodeudores, $request, &$caso) {
            
            // 2. Crear el Caso
            // $validated ahora incluye 'subproceso' (L4)
            $caso = Caso::create($validated);

            // 3. Sincronizar los codeudores
            $this->sincronizarCodeudores($caso, $datosCodeudores);

            // 4. Crear bitácora (lógica original)
            $caso->bitacoras()->create([
                'user_id'   => auth()->id(),
                'accion'    => $request->clonado_de_id ? 'Clonación de Caso' : 'Creación del Caso',
                'comentario'=> $request->clonado_de_id
                    ? 'El caso fue creado como un clon del caso #' . $request->clonado_de_id
                    : 'El caso ha sido registrado en el sistema.',
            ]);
            
        });
        // --- FIN: CAMBIO ---

        return to_route('casos.show', $caso->id)->with('success', '¡Caso registrado exitosamente!');
    }

    // ===== ESTE ES EL MÉTODO CRÍTICO QUE CORREGIMOS =====
    public function show(Caso $caso): Response
    {
        $this->authorize('view', $caso);

        $caso->load([
            // --- INICIO: CORRECCIÓN (POLIMORFISMO) ---
            // Cargamos el ID y nombre del deudor (que es una Persona)
            'deudor:id,nombre_completo',
            // Cargamos los codeudores (solo necesitamos sus datos base)
            'codeudores', 
            // --- FIN: CORRECCIÓN ---
            'cooperativa',
            'user',
            // --- INICIO: CORRECCIÓN (POLIMORFISMO) ---
            // Cargamos ambas relaciones polimórficas en los documentos.
            // La vista (Vue) decidirá cuál mostrar.
            'documentos' => function ($query) {
                $query->with(['persona:id,nombre_completo', 'codeudor:id,nombre_completo']);
            },
            // --- FIN: CORRECCIÓN ---
            'bitacoras.user',
            'documentosGenerados.usuario',
            
            // 'pagos.usuario', // <-- ¡ELIMINADO!
            
            'validacionesLegales.requisito',
            'juzgado',
            'especialidad',
            'actuaciones' => function ($query) {
                $query->with('user:id,name')->orderBy('fecha_actuacion', 'desc')->orderBy('created_at', 'desc');
            },
            
            // --- INICIO: CORRECCIÓN ---
            // Se debe seleccionar la clave foránea (caso_id) para que Eloquent
            // pueda "unir" la relación después de cargarla.
            'contrato:id,monto_total,caso_id' 
            // --- FIN: CORRECCIÓN ---
        ]);

        // ===== INICIO: NUEVA LÓGICA DE RESUMEN FINANCIERO =====
        $resumenFinanciero = [
            'monto_total'     => (float) $caso->monto_total, // Valor por defecto del caso
            'total_pagado'    => 0.0,
            'saldo_pendiente' => (float) $caso->monto_total,
        ];

        // Si el contrato existe (gracias al 'contrato:id,monto_total,caso_id' de arriba),
        // calcula las finanzas basado en él.
        if ($caso->contrato) {
            
            // Usamos la misma lógica de DB::table de tu ContratosController
            // ya que los modelos de pago y cargos del contrato no están implementados
            // como relaciones de Eloquent (según tu Contrato.php).
            
            $totalPagado = DB::table('contrato_pagos')
                                ->where('contrato_id', $caso->contrato->id)
                                ->sum('valor');

            $totalCargos = DB::table('contrato_cargos')
                                ->where('contrato_id', $caso->contrato->id)
                                ->sum('monto');
            
            // El Monto Total real es el del contrato (parte fija) + cargos (litis, gastos, etc.)
            $montoTotalContrato = (float) $caso->contrato->monto_total + (float) $totalCargos;

            $resumenFinanciero = [
                'monto_total'     => $montoTotalContrato,
                'total_pagado'    => (float) $totalPagado,
                'saldo_pendiente' => $montoTotalContrato - (float) $totalPagado,
            ];
        }
        // ===== FIN: NUEVA LÓGICA DE RESUMEN FINANCIERO =====

        $caso->setRelation('auditoria', $caso->bitacoras);

        $plantillasDisponibles = PlantillaDocumento::where('activa', true)
            ->where(function ($query) use ($caso) {
                $query->where('cooperativa_id', $caso->cooperativa_id)
                      ->orWhereNull('cooperativa_id');
            })
            ->where(function ($query) use ($caso) {
                $query->whereNull('aplica_a')
                      ->orWhereJsonLength('aplica_a', 0)
                      ->orWhereJsonContains('aplica_a', $caso->tipo_proceso);
            })
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'version']);

        return Inertia::render('Casos/Show', [
            'caso' => $caso,
            
            // --- ¡AÑADIDO! ---
            // Pasamos el nuevo resumen financiero a la vista.
            'resumen_financiero' => $resumenFinanciero, 
            
            'actuaciones' => $caso->actuaciones, // <-- Añadido
            'plantillas' => $plantillasDisponibles,
            'can' => [
                'update' => Auth::user()->can('update', $caso),
                'delete' => Auth::user()->can('delete', $caso),
            ],
        ]);
    }
    // ===== FIN DEL MÉTODO CORREGIDO =====

    public function edit(Caso $caso): Response
    {
        $this->authorize('update', $caso);
        $user = Auth::user();

        // --- INICIO: CAMBIO ---
        $caso->load([
            'juzgado', 
            'cooperativa', 
            'user', 
            'deudor', 
            // 'codeudor1', // <-- ELIMINADO
            // 'codeudor2', // <-- ELIMINADO
            'codeudores' // <-- AÑADIDO
        ]);
        // --- FIN: CAMBIO ---

        $cooperativas = ($user->tipo_usuario === 'admin')
            ? Cooperativa::select('id', 'nombre')->get()
            : $user->cooperativas()->select('id', 'nombre')->get();

        $abogadosYGestores = User::whereIn('tipo_usuario', ['abogado', 'gestor'])
            ->select('id', 'name')
            ->get();

        $personas = Persona::select('id', 'nombre_completo', 'numero_documento')->get();

        return Inertia::render('Casos/Edit', [
            'caso' => $caso,
            'cooperativas' => $cooperativas,
            'abogadosYGestores' => $abogadosYGestores,
            'personas' => $personas,
            
            // =================================================================
            // ===== ¡CAMBIO IMPORTANTE AQUÍ! (Carga de 4 NIVELES) =========
            // =================================================================
            // 'estructuraProcesal'  => EspecialidadJuridica::with('tiposProceso.subtipos')->orderBy('nombre')->get(), // <-- LÍNEA ANTIGUA (3 NIVELES)
            'estructuraProcesal'  => EspecialidadJuridica::with([
                                        'tiposProceso' => fn($q) => $q->orderBy('nombre'),
                                        'tiposProceso.subtipos' => fn($q) => $q->orderBy('nombre'),
                                        'tiposProceso.subtipos.subprocesos' => fn($q) => $q->orderBy('nombre') // <-- LÍNEA NUEVA (4 NIVELES)
                                    ])->orderBy('nombre')->get(),
            // =================================================================
            // =================== FIN DEL CAMBIO ==============================
            // =================================================================

            'etapas_procesales' => DB::table('etapas_procesales')->orderBy('nombre')->pluck('nombre')->all(),
        ]);
    }

    public function update(UpdateCasoRequest $request, Caso $caso): RedirectResponse
    {
        $this->authorize('update', $caso);

        // --- INICIO: CAMBIO (Lógica de Codeudores) ---
        $validated = $request->validated();
        
        // 1. Separar los datos de los codeudores
        $datosCodeudores = $validated['codeudores'] ?? [];
        unset($validated['codeudores']);

        DB::transaction(function () use ($caso, $validated, $datosCodeudores) {
            
            // 2. Actualizar el Caso
            // $validated ahora incluye 'subproceso' (L4)
            // y 'fecha_tasa_interes'
            $caso->update($validated);

            // 3. Sincronizar los codeudores
            $this->sincronizarCodeudores($caso, $datosCodeudores);

            // 4. Crear bitácora (lógica original)
            $caso->bitacoras()->create([
                'user_id'   => auth()->id(),
                'accion'    => 'Actualización de Caso',
                'comentario'=> 'Se actualizaron los datos principales del caso.',
            ]);

        });
        // --- FIN: CAMBIO ---

        return to_route('casos.show', $caso->id)->with('success', '¡Caso actualizado exitosamente!');
    }

    /**
     * Desbloquea un caso específico.
     */
    public function unlock(Request $request, Caso $caso): RedirectResponse
    {
        $user = Auth::user();

        // 1. Autorización: Verificamos que el usuario sea uno de los roles permitidos
        if (!in_array($user->tipo_usuario, ['admin', 'abogado', 'gestor'])) {
            return to_route('casos.show', $caso->id)
                ->with('error', 'No tienes permiso para desbloquear este caso.');
        }

        // 2. Autorización de Policy (Opcional pero recomendado):
        // Nos aseguramos que el abogado/gestor pertenezca a la cooperativa del caso.
        // Asumimos que tu CasoPolicy@update ya maneja esta lógica.
        $this->authorize('update', $caso);

        // 3. Ejecutar la acción
        $caso->update([
            'bloqueado' => false,
            'motivo_bloqueo' => null,
        ]);

        // 4. Registrar en bitácora
        $caso->bitacoras()->create([
            'user_id' => $user->id,
            'accion' => 'Desbloqueo de Caso',
            'comentario' => 'El caso ha sido desbloqueado por ' . $user->name,
        ]);

        return to_route('casos.show', $caso->id)
            ->with('success', '¡Caso desbloqueado exitosamente!');
    }

    public function destroy(Caso $caso): RedirectResponse
    {
        $this->authorize('delete', $caso);
        
        // --- INICIO: Eliminar actuaciones asociadas ---
        $caso->actuaciones()->delete();
        // --- FIN: Eliminar actuaciones asociadas ---

        // --- INICIO: CAMBIO (Limpiar tabla pivote) ---
        $caso->codeudores()->detach();
        // --- FIN: CAMBIO ---

        $caso->delete();

        return to_route('casos.index')->with('success', '¡Caso eliminado exitosamente!');
    }

    public function clonar(Caso $caso): Response
    {
        $this->authorize('create', Caso::class);
        $this->authorize('view', $caso);

        // --- INICIO: CAMBIO (CORRECCIÓN PARA CLONAR) ---
        // Cargamos todas las relaciones necesarias que 'Create.vue' espera en 'casoAClonar'
        $caso->load('juzgado', 'codeudores', 'cooperativa', 'user', 'deudor');
        // --- FIN: CAMBIO ---

        return Inertia::render('Casos/Create', [
            'casoAClonar' => $caso,
            'cooperativas' => Cooperativa::all(['id', 'nombre']),
            'abogadosYGestores' => User::whereIn('tipo_usuario', ['abogado', 'gestor'])->select('id', 'name')->get(),
            'personas' => Persona::select('id', 'nombre_completo', 'numero_documento')->get(),
            
            // =================================================================
            // ===== ¡CAMBIO IMPORTANTE AQUÍ! (Carga de 4 NIVELES) =========
            // =================================================================
            // 'estructuraProcesal'  => EspecialidadJuridica::with('tiposProceso.subtipos')->orderBy('nombre')->get(), // <-- LÍNEA ANTIGUA (3 NIVELES)
            'estructuraProcesal'  => EspecialidadJuridica::with([
                                        'tiposProceso' => fn($q) => $q->orderBy('nombre'),
                                        'tiposProceso.subtipos' => fn($q) => $q->orderBy('nombre'),
                                        'tiposProceso.subtipos.subprocesos' => fn($q) => $q->orderBy('nombre') // <-- LÍNEA NUEVA (4 NIVELES)
                                    ])->orderBy('nombre')->get(),
            // =================================================================
            // =================== FIN DEL CAMBIO ==============================
            // =================================================================

            'etapas_procesales' => DB::table('etapas_procesales')->orderBy('nombre')->pluck('nombre')->all(),
        ]);
    }

    // --- INICIO: Métodos CRUD para Actuaciones de Casos ---

    /**
     * Guarda una nueva actuación manual para un caso.
     */
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

    /**
     * Actualiza una actuación específica.
     */
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

    /**
     * Reabre un caso que estaba cerrado.
     */
    public function reopen(Request $request, Caso $caso): RedirectResponse
    {
        // 1. Autorización: Solo el admin puede reabrir
        if (Auth::user()->tipo_usuario !== 'admin') {
            return to_route('casos.edit', $caso->id)
                ->with('error', 'No tienes permiso para reabrir este caso.');
        }

        // 2. Ejecutar la acción de reapertura
        $caso->update([
            'estado_proceso' => 'prejurídico', // Vuelve al estado inicial
            'nota_cierre' => null,            // Borra la nota de cierre
        ]);

        // 3. Registrar en bitácora
        $caso->bitacoras()->create([
            'user_id' => auth()->id(),
            'accion' => 'Reapertura de Caso',
            'comentario' => 'El caso ha sido reabierto por ' . Auth::user()->name,
        ]);

        // 4. Redirigir de vuelta a la página de edición
        return to_route('casos.edit', $caso->id)
            ->with('success', '¡Caso reabierto exitosamente!');
    }

     /**
     * Cierra un caso que estaba activo.
     */
    public function close(Request $request, Caso $caso): RedirectResponse
    {
        $this->authorize('update', $caso);

        $validated = $request->validate([
            'nota_cierre' => ['required', 'string', 'max:2000'],
        ]);

        $caso->update([
            'estado_proceso' => 'cerrado',
            'nota_cierre' => $validated['nota_cierre'],
        ]);

        $caso->bitacoras()->create([
            'user_id' => auth()->id(),
            'accion' => 'Cierre de Caso',
            'comentario' => 'El caso ha sido cerrado por ' . Auth::user()->name . '. Motivo: ' . $validated['nota_cierre'],
        ]);

        return to_route('casos.edit', $caso->id)
            ->with('success', '¡Caso cerrado exitosamente!');
    }

    /**
     * Elimina una actuación específica.
     */
    public function destroyActuacion(Actuacion $actuacion)
    {
        $user = Auth::user();
        if (!$user || !in_array($user->tipo_usuario, ['admin', 'gestor', 'abogado'])) {
             abort(403, 'No autorizado para eliminar esta actuación.');
        }

        $actuacion->delete();

        return back(303)->with('success', 'Actuación eliminada.');
    }
    // --- FIN: Métodos CRUD para Actuaciones de Casos ---


    // --- INICIO: NUEVO MÉTODO PRIVADO PARA CODEUDORES (CON CORRECCIÓN) ---

    /**
     * Sincroniza los codeudores de un caso desde un array de datos.
     * Usa la tabla 'codeudores' y la lógica 'updateOrCreate'
     * basada en el 'numero_documento'.
     */
    private function sincronizarCodeudores(Caso $caso, array $datosCodeudores): void
    {
        // Usamos el namespace completo
        $CodeudorModel = \App\Models\Codeudor::class; 

        $idsCodeudores = []; // IDs de los codeudores que serán adjuntados

        foreach ($datosCodeudores as $datosPersona) {
            
            // 1. Crear o Actualizar el Codeudor en su propia tabla
            $codeudor = $CodeudorModel::updateOrCreate(
                // Llave de búsqueda:
                ['numero_documento' => $datosPersona['numero_documento']],
                // Datos a insertar o actualizar:
                [
                    'nombre_completo' => $datosPersona['nombre_completo'],
                    'tipo_documento' => $datosPersona['tipo_documento'] ?? 'CC',
                    'celular' => $datosPersona['celular'] ?? null,
                    
                    // ===== INICIO: CORRECIÓN =====
                    // El formulario y el Request envían 'correo', no 'correo_electronico'
                    'correo' => $datosPersona['correo'] ?? null,
                    // ===== FIN: CORRECIÓN =====
                    
                    'addresses' => $datosPersona['addresses'] ?? null, 
                    'social_links' => $datosPersona['social_links'] ?? null,
                ]
            );
            $idsCodeudores[] = $codeudor->id;
        }

        // 2. Sincronizar la tabla pivote 'caso_codeudor'
        // 'sync' adjunta los IDs del array, y quita los que ya no están.
        $caso->codeudores()->sync($idsCodeudores);
    }
    // --- FIN: NUEVO MÉTODO PRIVADO ---
}

