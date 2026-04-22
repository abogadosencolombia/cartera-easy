<?php

namespace App\Http\Controllers\Gestion\Honorarios;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Carbon\Carbon;
use App\Models\Caso;
use App\Models\Contrato;
use App\Models\Persona;
use App\Models\ProcesoRadicado;
use App\Models\Actuacion;
use App\Models\AuditoriaEvento;
use App\Traits\RegistraRevisionTrait;

class ContratosController extends Controller
{
    use RegistraRevisionTrait;
    public function index(Request $request)
    {
        $q = trim((string)$request->input('q',''));
        $estado = trim((string)$request->input('estado',''));
        $stats = ['activeValue'=>0,'activeCount'=>0,'closedCount'=>0];

        $contratosQuery = Contrato::query()
            ->where('estado', '!=', 'REESTRUCTURADO')
            ->with([
                'cliente:id,nombre_completo',
                'proceso:id,radicado',
                'caso:id',
            ])
            ->addSelect(['*', DB::raw('(SELECT SUM(valor) FROM contrato_pagos WHERE contrato_pagos.contrato_id = contratos.id) as total_pagado')]);

        if ($q !== '') {
            $contratosQuery->where(function ($query) use ($q) {
                $query->where('id', 'ilike', "%{$q}%")
                            ->orWhereHas('cliente', function ($subQuery) use ($q) {
                                $subQuery->where('nombre_completo', 'ilike', "%{$q}%");
                            });
            });
        }

        if ($estado !== '') {
            $contratosQuery->where('estado', $estado);
        }

        $contratos = $contratosQuery->orderByDesc('created_at')->paginate(10)
            ->through(fn ($contrato) => [
                'id' => $contrato->id,
                'cliente_id' => $contrato->cliente_id,
                'persona_nombre' => $contrato->cliente?->nombre_completo,
                'estado' => $contrato->estado,
                'monto_total' => $contrato->monto_total,
                'total_pagado' => $contrato->total_pagado ?? 0,
                'created_at' => $contrato->created_at,
                'modalidad' => $contrato->modalidad,
                'frecuencia_pago' => $contrato->frecuencia_pago, // Agregado al índice por si se requiere mostrar
                'caso_id' => $contrato->caso?->id,
                'proceso' => $contrato->proceso ? [
                    'id' => $contrato->proceso->id,
                    'numero' => $contrato->proceso->radicado ?: $contrato->proceso->id, 
                ] : null,
            ]);

        try {
            $active = Contrato::whereIn('estado',['ACTIVO', 'PAGOS_PENDIENTES', 'EN_MORA', 'PAGO_PARCIAL']);
            $stats['activeCount'] = (clone $active)->count();
            $stats['activeValue'] = (clone $active)->sum('monto_total');
            $stats['closedCount'] = Contrato::where('estado','CERRADO')->count();
        } catch (\Throwable $e) {
            Log::error("Error calculando estadísticas de contratos: " . $e->getMessage());
        }

        return Inertia::render('Gestion/Honorarios/Contratos/Index', [
            'contratos' => $contratos,
            'filters'   => ['q'=>$q,'estado'=>$estado],
            'stats'     => $stats,
        ]);
    }

    public function create(Request $request)
    {
        $plantilla = null;
        if ($request->has('from')) {
            $plantilla = Contrato::find($request->input('from'));
        }

        $proceso = null;
        $clienteSeleccionado = null;
        $datosCaso = null; 

        if ($request->has('caso_id')) {
            $caso = Caso::with('deudor:id,nombre_completo')->find($request->input('caso_id'));

            if ($caso) {
                $monto_para_contrato = $caso->monto_total;
                if ($request->has('monto')) {
                    $monto_para_contrato = (float) $request->input('monto');
                }
                $datosCaso = [
                    'id' => $caso->id,
                    'monto_total' => $monto_para_contrato, 
                ];
                if ($caso->deudor) {
                    $clienteSeleccionado = (object) [
                        'id' => $caso->deudor->id,
                        'nombre_completo' => $caso->deudor->nombre_completo,
                        'nombre' => $caso->deudor->nombre_completo,
                    ];
                }
            }
        }

        if ($request->has('proceso_id')) {
            $proceso = ProcesoRadicado::with('demandantes:id,nombre_completo')->find($request->input('proceso_id'));
        }

        if ($request->has('cliente_id')) {
            $clienteSeleccionado = Persona::select('id', 'nombre_completo')->find($request->input('cliente_id'));
        }
        elseif ($proceso && !$clienteSeleccionado) {
            // Intentar por ID directo (compatibilidad) o por relación pivot (nuevo sistema)
            $clienteSeleccionado = $proceso->demandante_id 
                ? Persona::select('id', 'nombre_completo')->find($proceso->demandante_id)
                : $proceso->demandantes->first();
        }

        $personas = [];
        $modalidades = ['CUOTAS','PAGO_UNICO','LITIS','CUOTA_MIXTA'];

        try {
            if (class_exists(Persona::class)) {
                $personas = Persona::select('id','nombre_completo as nombre')->orderBy('nombre_completo')->limit(500)->get();
            } else {
                $personas = DB::table('personas')->select('id','nombre_completo as nombre')->orderBy('nombre_completo')->limit(500)->get();
            }
        } catch (\Throwable $e) {
            Log::error("Error cargando lista de personas: " . $e->getMessage());
        }

        return Inertia::render('Gestion/Honorarios/Contratos/Create', [
            'clientes'        => $personas,
            'modalidades'     => $modalidades,
            'plantilla'       => $plantilla ? $plantilla->toArray() : null,
            'proceso'         => $proceso,
            'clienteSeleccionado' => $clienteSeleccionado,
            'datosCaso'       => $datosCaso,
        ]);
    }

    public function store(Request $request)
    {
        $rules = [
            'cliente_id' => ['required', 'exists:personas,id'],
            'modalidad'  => ['required', 'in:CUOTAS,PAGO_UNICO,LITIS,CUOTA_MIXTA'],
            'inicio'     => ['required', 'date'],
            'anticipo'   => ['nullable', 'numeric', 'min:0'],
            'nota'       => ['nullable', 'string', 'max:5000'],
            'contrato_origen_id' => ['nullable', 'integer', 'exists:contratos,id'],
            'proceso_id' => ['nullable', 'integer', 'exists:proceso_radicados,id'],
            'caso_id'    => ['nullable', 'integer', 'exists:casos,id'], 
            'manual_cuotas' => ['nullable', 'array'],
            'manual_cuotas.*.fecha' => ['required_with:manual_cuotas', 'date'],
            'manual_cuotas.*.valor' => ['required_with:manual_cuotas', 'numeric', 'min:0'],
            // ✅ CAMBIO REALIZADO: Agregada opción AL_FINALIZAR a la validación
            'frecuencia_pago' => ['nullable', 'in:DIARIO,SEMANAL,QUINCENAL,MENSUAL,AL_FINALIZAR'],
        ];

        $modalidad = $request->input('modalidad');

        if ($modalidad === 'CUOTAS' || $modalidad === 'PAGO_UNICO' || $modalidad === 'CUOTA_MIXTA') {
            $rules['monto_total'] = ['required', 'numeric', 'min:0'];
            $rules['cuotas']      = ['required', 'integer', 'min:1', 'max:120'];
        }

        if ($modalidad === 'LITIS' || $modalidad === 'CUOTA_MIXTA') {
            $rules['porcentaje_litis'] = ['required', 'numeric', 'min:0', 'max:100'];
        }

        $validatedData = Validator::make($request->all(), $rules)->validate();

        $monto_total = round((float)($validatedData['monto_total'] ?? 0), 2);
        $anticipo    = round((float)($validatedData['anticipo'] ?? 0), 2);

        if (in_array($modalidad, ['CUOTAS', 'PAGO_UNICO', 'CUOTA_MIXTA']) && $anticipo > $monto_total) {
            return back()->withErrors(['anticipo' => 'El anticipo no puede superar el monto total.'])->withInput();
        }

        // --- VALIDACIÓN MATEMÁTICA ESTRICTA DE CUOTAS MANUALES ---
        $manualCuotas = $request->input('manual_cuotas');
        if (!empty($manualCuotas) && $modalidad !== 'LITIS') {
            $netoEsperado = round($monto_total - $anticipo, 2);
            $sumaManual = 0;
            foreach ($manualCuotas as $mc) {
                $sumaManual += round((float)$mc['valor'], 2);
            }
            
            // Tolerancia de 1 peso por redondeos
            if (abs($netoEsperado - $sumaManual) > 1) {
                return back()->withErrors(['monto_total' => "La suma de las cuotas manuales ($sumaManual) no coincide con el neto a financiar ($netoEsperado)."])->withInput();
            }
        }

        $contrato = null;

        try {
            DB::transaction(function () use (&$contrato, $validatedData, $monto_total, $anticipo, $modalidad, $manualCuotas) {
                $contrato_origen_id = $validatedData['contrato_origen_id'] ?? null;

                if ($contrato_origen_id) {
                    Contrato::where('id', $contrato_origen_id)->update(['estado' => 'REESTRUCTURADO']);
                }

                $contrato = Contrato::create([
                    'cliente_id'        => $validatedData['cliente_id'],
                    'monto_total'       => $monto_total,
                    'anticipo'          => $anticipo,
                    'porcentaje_litis'  => $validatedData['porcentaje_litis'] ?? null,
                    'monto_base_litis'  => null,
                    'modalidad'         => $modalidad,
                    'frecuencia_pago'   => $validatedData['frecuencia_pago'] ?? 'MENSUAL', // Guardamos la frecuencia
                    'estado'            => 'ACTIVO',
                    'inicio'            => $validatedData['inicio'],
                    'nota'              => $validatedData['nota'] ?? null,
                    'contrato_origen_id' => $contrato_origen_id,
                    'proceso_id'        => $validatedData['proceso_id'] ?? null,
                    'caso_id'           => $validatedData['caso_id'] ?? null,
                ]);

                if ($modalidad !== 'LITIS') {
                    $neto = max(0, $monto_total - $anticipo);
                    $cuotasData = [];

                    // --- USAR CUOTAS MANUALES SI EXISTEN ---
                    if (!empty($manualCuotas)) {
                        foreach ($manualCuotas as $idx => $mc) {
                            $cuotasData[] = [
                                'contrato_id'       => $contrato->id,
                                'numero'            => $idx + 1,
                                'fecha_vencimiento' => $mc['fecha'],
                                'valor'             => $mc['valor'],
                                'estado'            => $neto > 0 ? 'PENDIENTE' : 'PAGADA',
                                'created_at'        => now(),
                                'updated_at'        => now(),
                            ];
                        }
                    } 
                    // --- GENERACIÓN AUTOMÁTICA (FALLBACK SERVIDOR) ---
                    // Esto se ejecuta si por alguna razón no llegan cuotas manuales
                    else {
                        $cuotasCount = ($modalidad === 'PAGO_UNICO') ? 1 : (int)($validatedData['cuotas'] ?? 1);
                        if ($cuotasCount < 1) $cuotasCount = 1;
                        $frecuencia = $validatedData['frecuencia_pago'] ?? 'MENSUAL';

                        if ($neto > 0 || $modalidad === 'PAGO_UNICO') {
                            $netoCents  = (int) round($neto * 100);
                            $baseCents  = intdiv($netoCents, $cuotasCount);
                            $restoCents = $netoCents % $cuotasCount;
                            $fechaActual = Carbon::parse($validatedData['inicio']);

                            for ($i = 1; $i <= $cuotasCount; $i++) {
                                $valorCents = $baseCents + ($i <= $restoCents ? 1 : 0);
                                $valor      = $valorCents / 100.0;

                                // LÓGICA DE FECHAS SEGÚN FRECUENCIA
                                $fechaVencimiento = $fechaActual->copy();
                                $offset = $i - 1; // La primera cuota es la fecha de inicio

                                switch ($frecuencia) {
                                    case 'DIARIO':
                                        $fechaVencimiento->addDays($offset);
                                        break;
                                    case 'SEMANAL':
                                        $fechaVencimiento->addWeeks($offset);
                                        break;
                                    case 'QUINCENAL':
                                        $fechaVencimiento->addDays($offset * 15);
                                        break;
                                    case 'MENSUAL':
                                    case 'AL_FINALIZAR': // Fallback a mensual para cálculo
                                    default:
                                        $fechaVencimiento->addMonthsNoOverflow($offset);
                                        break;
                                }

                                $cuotasData[] = [
                                    'contrato_id'       => $contrato->id,
                                    'numero'            => $i,
                                    'fecha_vencimiento' => $fechaVencimiento->toDateString(),
                                    'valor'             => $valor,
                                    'estado'            => $neto > 0 ? 'PENDIENTE' : 'PAGADA',
                                    'created_at'        => now(),
                                    'updated_at'        => now(),
                                ];
                            }
                        }
                    }

                    if (!empty($cuotasData)) {
                        DB::table('contrato_cuotas')->insert($cuotasData);
                    }
                }

                // ✅ AUDITORÍA GLOBAL
                AuditoriaEvento::create([
                    'user_id' => Auth::id(),
                    'evento' => 'CREAR_CONTRATO',
                    'descripcion_breve' => "Creado contrato #{$contrato->id} ({$modalidad} - " . ($validatedData['frecuencia_pago'] ?? 'MENSUAL') . ") por $" . number_format($monto_total, 2),
                    'criticidad' => 'media',
                    'direccion_ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            });

            if ($contrato->caso_id) {
                return redirect()->route('casos.show', $contrato->caso_id)->with('success', 'Contrato creado y asociado al caso exitosamente.');
            }

            return redirect()->route('honorarios.contratos.show', $contrato->id)->with('success', 'Contrato creado exitosamente.');

        } catch (\Exception $e) {
            Log::error("Error al crear contrato: " . $e->getMessage());
            return back()->with('error', 'Ocurrió un error al crear el contrato: ' . $e->getMessage())->withInput();
        }
    }

    public function show($id)
    {
        $contrato = Contrato::with([
                'cliente:id,nombre_completo', 
                'contratoOrigen:id,estado,monto_total,modalidad',
                'actuaciones' => function ($query) {
                    $query->with('user:id,name')->orderBy('created_at', 'desc');
                },
                'proceso:id,radicado',
                'caso:id',
            ])
            ->findOrFail($id);

        // Registro automático de revisión diaria
        $this->registrarRevisionAutomatica($contrato);

        $contratoOrigen = $contrato->contratoOrigen;
        $cliente = $contrato->cliente;
        $actuaciones = $contrato->actuaciones;

        $clientes = [];
        $modalidades = ['CUOTAS','PAGO_UNICO','LITIS','CUOTA_MIXTA'];

        try {
            if (class_exists(Persona::class)) {
                $clientes = Persona::select('id','nombre_completo as nombre')->orderBy('nombre_completo')->limit(500)->get();
            } else {
                $clientes = DB::table('personas')->select('id','nombre_completo as nombre')->orderBy('nombre_completo')->limit(500)->get();
            }
        } catch (\Throwable $e) {}

        $total_cargos_valor = DB::table('contrato_cargos')->where('contrato_id', $id)->sum('monto');
        $total_pagos_valor  = DB::table('contrato_pagos')->where('contrato_id', $id)->sum('valor');

        $cuotas = DB::table('contrato_cuotas')
            ->where('contrato_id', $id)
            ->orderBy('numero')
            ->paginate(15, ['*'], 'cuotasPage');

        $cargos = DB::table('contrato_cargos as c')
            ->leftJoin('contrato_pagos as p', 'c.pago_id', '=', 'p.id')
            ->where('c.contrato_id', $id)
            ->select('c.*', 'p.fecha as fecha_pago_cargo', 'p.metodo as metodo_pago_cargo', 'p.nota as nota_pago_cargo', 'p.comprobante as comprobante_pago_cargo', 'p.id as pago_id_del_cargo') 
            ->orderByDesc('c.fecha_aplicado')
            ->paginate(15, ['*'], 'cargosPage');

        $pagos = DB::table('contrato_pagos')
            ->where('contrato_id', $id)
            ->orderByDesc('fecha')->orderByDesc('id')
            ->paginate(15, ['*'], 'pagosPage');

        return Inertia::render('Gestion/Honorarios/Contratos/Show', [
            'contrato' => $contrato,
            'contratoOrigen' => $contratoOrigen,
            'cliente' => $cliente,
            'cuotas' => $cuotas,
            'pagos' => $pagos,
            'cargos' => $cargos,
            'actuaciones' => $actuaciones,
            'total_cargos_valor' => $total_cargos_valor,
            'total_pagos_valor' => $total_pagos_valor,
            'clientes' => $clientes,
            'modalidades' => $modalidades
        ]);
    }

    public function reestructurar($id)
    {
        $contrato = Contrato::find($id);
        if (!$contrato) {
            return redirect()->route('honorarios.contratos.index')->with('error', 'Contrato no encontrado.');
        }
        
        // ✅ AUDITORÍA GLOBAL
        AuditoriaEvento::create([
            'user_id' => Auth::id(),
            'evento' => 'INICIO_REESTRUCTURACION',
            'descripcion_breve' => "Usuario inició proceso de reestructuración para contrato #{$id}",
            'criticidad' => 'media',
            'direccion_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return redirect()->route('honorarios.contratos.create', ['from' => $id]);
    }

    public function pagar($id, Request $request)
    {
        $validated = Validator::make($request->all(), [
            'cuota_id'    => ['required','integer'],
            'valor'       => ['required','numeric','min:0.01'],
            'fecha'       => ['required','date'],
            'metodo'      => ['required','in:EFECTIVO,TRANSFERENCIA,TARJETA,OTRO'],
            'nota'        => ['nullable','string','max:1000'],
            'comprobante' => ['nullable','file','mimes:pdf,jpg,jpeg,png,webp','max:5120'],
        ])->validate();

        $contrato = Contrato::with('caso')->findOrFail($id);

        $cuota = DB::table('contrato_cuotas')
            ->where('id', $validated['cuota_id'])
            ->where('contrato_id', $id)
            ->first();
        if (!$cuota) return back()->withErrors(['cuota_id'=>'Cuota no encontrado.']);

        $valor = round((float)$validated['valor'], 2);
        
        $path = null;
        if ($request->hasFile('comprobante')) {
            $path = $request->file('comprobante')->store("comprobantes/pagos/{$id}", 'public');
        }

        DB::transaction(function () use ($id, $contrato, $cuota, $validated, $valor, $path) {
            DB::table('contrato_pagos')->insert([
                'contrato_id' => $id,
                'cuota_id'    => $cuota->id,
                'cargo_id'    => null,
                'valor'       => $valor,
                'fecha'       => $validated['fecha'],
                'metodo'      => $validated['metodo'],
                'nota'        => $validated['nota'] ?? '',
                'comprobante' => $path,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            $totalPagadoCuota = DB::table('contrato_pagos')->where('cuota_id', $cuota->id)->sum('valor');
            $valorTotalCuota = (float)($cuota->valor ?? 0) + (float)($cuota->intereses_mora_acumulados ?? 0);

            $epsilon = 0.001; 
            if ($totalPagadoCuota >= ($valorTotalCuota - $epsilon)) {
                DB::table('contrato_cuotas')->where('id',$cuota->id)->update([
                    'estado'     => 'PAGADA',
                    'fecha_pago' => $validated['fecha'],
                    'updated_at' => now(),
                ]);
            } else {
                 DB::table('contrato_cuotas')->where('id',$cuota->id)->update([
                    'estado'     => 'PAGO_PARCIAL', 
                    'updated_at' => now(),
                ]);
            }

            $this->checkAndCloseContract($id);

            // Sincronización Caso
            if ($contrato->caso) {
                $nuevoTotalPagadoCaso = (float)$contrato->caso->monto_total_pagado + $valor;
                $nuevaDeudaCaso = max(0, (float)$contrato->caso->monto_deuda_actual - $valor);
                $contrato->caso->update([
                    'monto_total_pagado' => $nuevoTotalPagadoCaso,
                    'monto_deuda_actual' => $nuevaDeudaCaso,
                ]);
            }

            // ✅ AUDITORÍA GLOBAL
            AuditoriaEvento::create([
                'user_id' => Auth::id(),
                'evento' => 'REGISTRAR_PAGO_CUOTA',
                'descripcion_breve' => "Pago de $" . number_format($valor, 2) . " a cuota #{$cuota->numero} del contrato #{$id}",
                'criticidad' => 'alta', // Pagos son críticos
                'direccion_ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        });

        // ✅ Acción cuenta como revisión
        $contrato = Contrato::find($id);
        $this->registrarRevisionAutomatica($contrato);

        return back()->with('success','Pago registrado y caso sincronizado.');
    }

    public function resolverLitis($id, Request $request)
    {
        $validated = Validator::make($request->all(), [
            'monto_base_litis' => ['required', 'numeric', 'min:0'],
            'fecha_inicio_intereses' => ['nullable', 'date'],
        ])->validate();

        $contrato = Contrato::findOrFail($id);

        if ($contrato->modalidad !== 'LITIS' && $contrato->modalidad !== 'CUOTA_MIXTA') {
            return back()->with('error', 'Esta acción solo es válida para contratos de tipo Litis o Cuota Mixta.');
        }
        
        if (!is_null($contrato->monto_base_litis)) {
             return back()->with('error', 'Este caso de Litis ya fue resuelto y tiene un monto base asignado.');
        }

        $monto_base = round((float)$validated['monto_base_litis'], 2);
        $porcentaje = (float)$contrato->porcentaje_litis;
        $honorarios = round(($monto_base * $porcentaje) / 100, 2);

        DB::transaction(function () use ($contrato, $monto_base, $honorarios, $validated) {
            $contrato->update([
                'monto_base_litis' => $monto_base,
                'litis_valor_ganado' => $honorarios
            ]);

            if ($honorarios > 0) {
                DB::table('contrato_cargos')->insert([
                    'contrato_id'        => $contrato->id,
                    'tipo'               => 'LITIS',
                    'monto'              => $honorarios,
                    'estado'             => 'PENDIENTE',
                    'descripcion'        => "Honorarios del {$contrato->porcentaje_litis}% sobre un monto base de {$monto_base}.",
                    'fecha_aplicado'     => now()->toDateString(),
                    'fecha_inicio_intereses' => $validated['fecha_inicio_intereses'] ?? null,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);
                 if ($contrato->estado !== 'PAGOS_PENDIENTES') {
                     $contrato->update(['estado' => 'PAGOS_PENDIENTES']);
                 }
            } else {
                $this->checkAndCloseContract($contrato->id);
            }

            // ✅ AUDITORÍA GLOBAL
            AuditoriaEvento::create([
                'user_id' => Auth::id(),
                'evento' => 'RESOLVER_LITIS',
                'descripcion_breve' => "Litis resuelta contrato #{$contrato->id}. Base: ${monto_base}, Honorarios: ${honorarios}",
                'criticidad' => 'alta',
                'direccion_ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        });

        return back()->with('success', 'Resultado del caso registrado. Se ha generado el cargo por honorarios.');
    }

    public function pagarCargo($contrato_id, Request $request)
    {
        $validated = Validator::make($request->all(), [
            'cargo_id'    => ['required','integer'],
            'valor'       => ['required','numeric','min:0.01'],
            'fecha'       => ['required','date'],
            'metodo'      => ['required','in:EFECTIVO,TRANSFERENCIA,TARJETA,OTRO'],
            'nota'        => ['nullable','string','max:1000'],
            'comprobante' => ['required','file','mimes:pdf,jpg,jpeg,png,webp','max:5120'],
        ])->validate();

        $contrato = Contrato::with('caso')->findOrFail($contrato_id);

        $cargo = DB::table('contrato_cargos')
            ->where('id', $validated['cargo_id'])
            ->where('contrato_id', $contrato_id)
            ->first();
        if (!$cargo) return back()->withErrors(['cargo_id'=>'Cargo no encontrado.']);
        
        if ($cargo->estado === 'PAGADO' || !is_null($cargo->pago_id)) {
            return back()->withErrors(['cargo_id'=>'Este cargo ya ha sido pagado.']);
        }

        $valor = round((float)$validated['valor'], 2);
        $valorMinimo = (float)($cargo->monto ?? 0) + (float)($cargo->intereses_mora_acumulados ?? 0);
        
        if ($valor + 0.01 < $valorMinimo) {
            return back()->withErrors(['valor'=>'El pago debe ser igual o superior al valor pendiente del cargo (incluyendo mora).']);
        }

        $path = $request->file('comprobante')->store("comprobantes/cargos_pagos/{$contrato_id}", 'public');

        DB::transaction(function () use ($contrato_id, $contrato, $cargo, $validated, $valor, $path) {
            $pagoId = DB::table('contrato_pagos')->insertGetId([
                'contrato_id' => $contrato_id,
                'cuota_id'    => null,
                'cargo_id'    => $cargo->id,
                'valor'       => $valor,
                'fecha'       => $validated['fecha'],
                'metodo'      => $validated['metodo'],
                'nota'        => $validated['nota'] ?? '',
                'comprobante' => $path,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            DB::table('contrato_cargos')->where('id',$cargo->id)->update([
                'estado'     => 'PAGADO',
                'fecha_pago' => $validated['fecha'],
                'pago_id'    => $pagoId,
                'updated_at' => now(),
            ]);

            $this->checkAndCloseContract($contrato_id);

            // Sincronización Caso
            if ($contrato->caso) {
                $nuevoTotalPagadoCaso = (float)$contrato->caso->monto_total_pagado + $valor;
                $nuevaDeudaCaso = max(0, (float)$contrato->caso->monto_deuda_actual - $valor);
                $contrato->caso->update([
                    'monto_total_pagado' => $nuevoTotalPagadoCaso,
                    'monto_deuda_actual' => $nuevaDeudaCaso,
                ]);
            }

            // ✅ AUDITORÍA GLOBAL
            AuditoriaEvento::create([
                'user_id' => Auth::id(),
                'evento' => 'PAGO_CARGO',
                'descripcion_breve' => "Pago de cargo '{$cargo->descripcion}' en contrato #{$contrato_id} por $" . number_format($valor, 2),
                'criticidad' => 'alta',
                'direccion_ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        });

        return back()->with('success','Pago de cargo registrado.');
    }

    public function agregarCargo($id, Request $request)
    {
        $validated = Validator::make($request->all(), [
            'monto'              => ['required','numeric','min:0.01'],
            'descripcion'        => ['required','string','max:255'],
            'fecha'              => ['required','date'],
            'comprobante'        => ['nullable','file','mimes:pdf,jpg,jpeg,png,webp','max:5120'],
            'fecha_inicio_intereses' => ['nullable', 'date', 'after_or_equal:fecha'],
        ])->validate();

        $contrato = Contrato::findOrFail($id);
        
        if ($contrato->estado === 'CERRADO') {
             return back()->with('error', 'No se pueden añadir cargos a un contrato cerrado. Debe reabrirlo primero.');
        }

        $path = null;
        if ($request->hasFile('comprobante')) {
            $path = $request->file('comprobante')->store("comprobantes/cargos/{$id}", 'public');
        }

        DB::table('contrato_cargos')->insert([
            'contrato_id'        => $id,
            'tipo'               => 'GASTO', 
            'monto'              => $validated['monto'],
            'estado'             => 'PENDIENTE',
            'descripcion'        => $validated['descripcion'],
            'comprobante'        => $path,
            'fecha_aplicado'     => $validated['fecha'], 
            'fecha_inicio_intereses' => $validated['fecha_inicio_intereses'] ?? null,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        if ($contrato->estado === 'ACTIVO') {
             $contrato->update(['estado' => 'PAGOS_PENDIENTES']);
        }

        // ✅ AUDITORÍA GLOBAL
        AuditoriaEvento::create([
            'user_id' => Auth::id(),
            'evento' => 'AGREGAR_CARGO',
            'descripcion_breve' => "Cargo adicional agregado al contrato #{$id}: {$validated['descripcion']} ($" . number_format($validated['monto'], 2) . ")",
            'criticidad' => 'media',
            'direccion_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return back()->with('success','Cargo añadido.');
    }

    public function activar($id)
    {
        $contrato = Contrato::findOrFail($id);
        $contrato->update(['estado'=>'ACTIVO']);
        
        // ✅ AUDITORÍA GLOBAL
        AuditoriaEvento::create([
            'user_id' => Auth::id(),
            'evento' => 'ACTIVAR_CONTRATO',
            'descripcion_breve' => "Contrato #{$id} reactivado manualmente",
            'criticidad' => 'media',
            'direccion_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return back()->with('success','Contrato activado.');
    }

    public function cerrar($id, Request $request)
    {
        $validated = Validator::make($request->all(), [
            'monto'              => ['nullable','numeric','min:0'],
            'descripcion'        => ['required_with:monto','nullable','string','max:255'],
            'fecha_inicio_intereses' => ['nullable', 'date'],
        ])->validate();

        DB::transaction(function () use ($id, $validated) {
            $monto = $validated['monto'] ?? null;
            $descripcion = $validated['descripcion'] ?? null;

            if ($monto !== null && $monto > 0) {
                DB::table('contrato_cargos')->insert([
                    'contrato_id'        => $id,
                    'tipo'               => 'CIERRE_ATIPICO', 
                    'monto'              => $monto,
                    'estado'             => 'PENDIENTE',
                    'descripcion'        => $descripcion ?: 'Cargo por cierre manual.',
                    'fecha_aplicado'     => now()->toDateString(),
                    'fecha_inicio_intereses' => $validated['fecha_inicio_intereses'] ?? null,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);
            }

            $this->checkAndCloseContract($id, true); 

            // ✅ AUDITORÍA GLOBAL
            AuditoriaEvento::create([
                'user_id' => Auth::id(),
                'evento' => 'CERRAR_CONTRATO',
                'descripcion_breve' => "Cierre manual del contrato #{$id}",
                'criticidad' => 'media',
                'direccion_ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        });

        return back()->with('success','Contrato actualizado.');
    }

    public function saldarContrato($id)
    {
        $contrato = Contrato::findOrFail($id); 
        
        $cuotasPendientes = DB::table('contrato_cuotas')->where('contrato_id',$id)->where('estado','!=','PAGADA')->count();
        $cargosPendientes = DB::table('contrato_cargos')->where('contrato_id',$id)->where('estado','!=','PAGADO')->count();
        
        if ($cuotasPendientes > 0 || $cargosPendientes > 0) {
            return back()->with('error', 'No se puede saldar. Aún existen deudas pendientes.');
        }

        $contrato->update(['estado'=>'CERRADO']);

        // ✅ AUDITORÍA GLOBAL
        AuditoriaEvento::create([
            'user_id' => Auth::id(),
            'evento' => 'SALDAR_CONTRATO',
            'descripcion_breve' => "Contrato #{$id} marcado como SALDADO y CERRADO",
            'criticidad' => 'media',
            'direccion_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return back()->with('success','Contrato saldado y cerrado.');
    }

    public function reabrir($id)
    {
        $contrato = Contrato::findOrFail($id); 
        
        $cuotasPendientes = DB::table('contrato_cuotas')->where('contrato_id',$id)->where('estado','!=','PAGADA')->count();
        $cargosPendientes = DB::table('contrato_cargos')->where('contrato_id',$id)->where('estado','!=','PAGADO')->count();
        
        $nuevoEstado = ($cuotasPendientes > 0 || $cargosPendientes > 0) ? 'PAGOS_PENDIENTES' : 'ACTIVO';
        
        $contrato->update(['estado' => $nuevoEstado]);

        // ✅ AUDITORÍA GLOBAL
        AuditoriaEvento::create([
            'user_id' => Auth::id(),
            'evento' => 'REABRIR_CONTRATO',
            'descripcion_breve' => "Contrato #{$id} reabierto a estado {$nuevoEstado}",
            'criticidad' => 'media',
            'direccion_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return back()->with('success',"Contrato reabierto. Estado actualizado a: {$nuevoEstado}.");
    }

    public function verComprobante($pago_id)
    {
        $pago = DB::table('contrato_pagos')->find($pago_id);
        if (!$pago || !$pago->comprobante) abort(404,'Comprobante no encontrado.');
        if (!Storage::disk('public')->exists($pago->comprobante)) abort(404,'Archivo no encontrado.');
        return Storage::disk('public')->response($pago->comprobante);
    }

    public function verCargoComprobante($cargo_id)
    {
        $cargo = DB::table('contrato_cargos')->find($cargo_id);
        if (!$cargo || !$cargo->comprobante) abort(404,'Comprobante no encontrado.');
        if (!Storage::disk('public')->exists($cargo->comprobante)) abort(404,'Archivo no encontrado.');
        return Storage::disk('public')->response($cargo->comprobante);
    }

    public function pdfContrato($id)
    {
        $contrato = Contrato::with('cliente:id,nombre_completo')->findOrFail($id);
        $cliente = $contrato->cliente;
        $cuotas = DB::table('contrato_cuotas')->where('contrato_id', $id)->orderBy('numero')->get(); 

        $data = [
            'contrato' => $contrato, 
            'cliente' => $cliente, 
            'cuotas' => $cuotas 
        ];

        if (class_exists('\\Barryvdh\\DomPDF\\Facade\\Pdf')) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('honorarios.contrato', $data); 
            return $pdf->stream("contrato_{$id}.pdf");
        }
        return response()->view('honorarios.contrato', $data);
    }

    public function pdfLiquidacion($id)
    {
        $contrato = Contrato::with('cliente:id,nombre_completo')->findOrFail($id);
        $cliente = $contrato->cliente;

        $pagos = DB::table('contrato_pagos as p')
            ->leftJoin('contrato_cuotas as c', 'p.cuota_id', '=', 'c.id')
            ->where('p.contrato_id', $id)
            ->select('p.*', 'c.numero as cuota_numero')
            ->orderBy('p.fecha')
            ->get();

        $cargosPagados = DB::table('contrato_cargos as cg')
            ->join('contrato_pagos as p', 'cg.pago_id', '=', 'p.id')
            ->where('cg.contrato_id', $id)
            ->where('cg.estado', 'PAGADO')
            ->select('cg.*', 'p.fecha as fecha_pago_cargo', 'p.nota as nota_pago_cargo')
            ->get();

        $totalCargosValor = DB::table('contrato_cargos')->where('contrato_id', $id)->sum('monto');
        $totalPagado = DB::table('contrato_pagos')->where('contrato_id', $id)->sum('valor');
        $totalMoraPendiente = DB::table('contrato_cuotas')->where('contrato_id', $id)->where('estado', '!=', 'PAGADA')->sum('intereses_mora_acumulados');
        $totalMoraCargos = DB::table('contrato_cargos')->where('contrato_id', $id)->where('estado', '!=', 'PAGADA')->sum('intereses_mora_acumulados');
        $totalMora = $totalMoraPendiente + $totalMoraCargos;
        $saldoPendiente = ($contrato->monto_total + $totalCargosValor + $totalMora) - $totalPagado;

        $cuotasPendientes = DB::table('contrato_cuotas')->where('contrato_id', $id)->where('estado', '!=', 'PAGADA')->orderBy('numero')->get();
        $cargosPendientes = DB::table('contrato_cargos')->where('contrato_id', $id)->where('estado', '!=', 'PAGADA')->orderBy('fecha_aplicado')->get();

        $data = [
            'contrato'      => $contrato,
            'cliente'       => $cliente,
            'pagos'         => $pagos,
            'cargosPagados' => $cargosPagados,
            'cuotasPendientes' => $cuotasPendientes,
            'cargosPendientes' => $cargosPendientes,
            'totalCargosValor' => $totalCargosValor, 
            'totalPagado'      => $totalPagado,
            'saldoPendiente'   => $saldoPendiente,
            'granTotal'        => $contrato->monto_total + $totalCargosValor + $totalMora, 
            'totalMora'        => $totalMora, 
        ];

        if (class_exists('\\Barryvdh\\DomPDF\\Facade\\Pdf')) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('honorarios.liquidacion', $data);
            return $pdf->stream("liquidacion_{$id}.pdf");
        }

        return response()->view('honorarios.liquidacion', $data);
    }

    private function checkAndCloseContract($contrato_id, $isManualClosure = false)
    {
        $cuotasPendientes = DB::table('contrato_cuotas')->where('contrato_id',$contrato_id)->where('estado','!=','PAGADA')->count();
        $cargosPendientes = DB::table('contrato_cargos')->where('contrato_id',$contrato_id)->where('estado','!=','PAGADO')->count();

        $contrato = Contrato::find($contrato_id);
        if (!$contrato) return; 

        if ($cuotasPendientes === 0 && $cargosPendientes === 0) {
            if (in_array($contrato->modalidad, ['LITIS', 'CUOTA_MIXTA'])) {
                if (is_null($contrato->monto_base_litis)) {
                        if ($contrato->estado !== 'ACTIVO') {
                            $contrato->update(['estado'=>'ACTIVO']);
                        }
                        return; 
                }
            }
            if ($contrato->estado !== 'CERRADO') {
                $contrato->update(['estado'=>'CERRADO']);
            }
        } elseif ($isManualClosure) {
            if ($contrato->estado !== 'PAGOS_PENDIENTES') {
                $contrato->update(['estado'=>'PAGOS_PENDIENTES']);
            }
        }
    }

    public function subirDocumento($id, Request $request)
    {
        $validated = Validator::make($request->all(), [
            'documento' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ])->validate();

        $contrato = Contrato::findOrFail($id);

        DB::transaction(function () use ($contrato, $request, $validated) {
            if ($contrato->documento_contrato) {
                Storage::disk('public')->delete($contrato->documento_contrato);
            }

            $path = $request->file('documento')->store("documentos/contratos/{$contrato->id}", 'public');
            $contrato->update(['documento_contrato' => $path]);

            // ✅ AUDITORÍA GLOBAL
            AuditoriaEvento::create([
                'user_id' => Auth::id(),
                'evento' => 'SUBIR_DOCUMENTO_CONTRATO',
                'descripcion_breve' => "Se subió documento PDF al contrato #{$contrato->id}",
                'criticidad' => 'media',
                'direccion_ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        });

        return back()->with('success', 'Documento del contrato subido correctamente.');
    }

    public function verDocumento($id)
    {
        $contrato = Contrato::findOrFail($id);
        if (!$contrato->documento_contrato) abort(404, 'Documento no encontrado.');
        if (!Storage::disk('public')->exists($contrato->documento_contrato)) abort(404, 'Archivo no encontrado en el disco.');
        return Storage::disk('public')->response($contrato->documento_contrato);
    }

    public function eliminarDocumento($id)
    {
        $contrato = Contrato::findOrFail($id);

        if (!$contrato->documento_contrato) {
            return back()->with('info', 'No hay documento para eliminar.');
        }

        DB::transaction(function () use ($contrato) {
            Storage::disk('public')->delete($contrato->documento_contrato);
            $contrato->update(['documento_contrato' => null]);

            // ✅ AUDITORÍA GLOBAL
            AuditoriaEvento::create([
                'user_id' => Auth::id(),
                'evento' => 'ELIMINAR_DOCUMENTO_CONTRATO',
                'descripcion_breve' => "Se eliminó el documento PDF del contrato #{$contrato->id}",
                'criticidad' => 'alta',
                'direccion_ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        });

        return back()->with('success', 'Documento eliminado correctamente.');
    }

    public function destroy($id)
    {
        // --- SEGURIDAD: SOLO ADMINS PUEDEN BORRAR ---
        if (Auth::user()->tipo_usuario !== 'admin') {
            // ✅ AUDITORÍA: INTENTO FALLIDO
            AuditoriaEvento::create([
                'user_id' => Auth::id(),
                'evento' => 'INTENTO_ELIMINAR_CONTRATO',
                'descripcion_breve' => "Usuario no autorizado intentó eliminar contrato #{$id}",
                'criticidad' => 'alta',
                'direccion_ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
            return back()->with('error', 'Acción no autorizada. Solo los administradores pueden eliminar contratos.');
        }

        $contrato = Contrato::findOrFail($id);
        
        DB::transaction(function () use ($contrato, $id) {
            if ($contrato->documento_contrato) {
                Storage::disk('public')->delete($contrato->documento_contrato);
            }

            DB::table('contrato_cuotas')->where('contrato_id', $contrato->id)->delete();
            DB::table('contrato_cargos')->where('contrato_id', $contrato->id)->delete();
            DB::table('contrato_pagos')->where('contrato_id', $contrato->id)->delete();
            Actuacion::where('actuable_type', Contrato::class)->where('actuable_id', $contrato->id)->delete();

            $contrato->delete();

            // ✅ AUDITORÍA GLOBAL: ÉXITO
            AuditoriaEvento::create([
                'user_id' => Auth::id(),
                'evento' => 'ELIMINAR_CONTRATO',
                'descripcion_breve' => "Administrador eliminó permanentemente contrato #{$id} y sus registros asociados",
                'criticidad' => 'alta',
                'direccion_ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        });

        return redirect()->route('honorarios.contratos.index')->with('success', "Contrato #{$id} eliminado permanentemente.");
    }

    public function storeActuacion(Request $request, $id)
    {
        $validated = $request->validate([
            'nota' => ['required', 'string', 'max:5000'],
            'fecha_actuacion' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $contrato = Contrato::findOrFail($id);

        $contrato->actuaciones()->create([
            'nota' => $validated['nota'],
            'fecha_actuacion' => $validated['fecha_actuacion'],
            'user_id' => Auth::id(),
        ]);

        // ✅ AUDITORÍA GLOBAL
        AuditoriaEvento::create([
            'user_id' => Auth::id(),
            'evento' => 'ACTUACION_CONTRATO',
            'descripcion_breve' => "Nueva actuación en contrato #{$id}",
            'criticidad' => 'baja',
            'direccion_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return back(303)->with('success', 'Actuación registrada.'); 
    }

    public function updateActuacion(Request $request, Actuacion $actuacion)
    {
        $user = Auth::user();
        if ($actuacion->actuable_type !== Contrato::class || !$user || !in_array($user->tipo_usuario, ['admin', 'gestor', 'abogado'])) {
             abort(403, 'No autorizado para editar esta actuación.');
        }

        $validated = $request->validate([
            'nota' => ['required', 'string', 'max:5000'],
            'fecha_actuacion' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $actuacion->update($validated);

        // ✅ AUDITORÍA GLOBAL
        if ($actuacion->actuable instanceof Contrato) {
            AuditoriaEvento::create([
                'user_id' => Auth::id(),
                'evento' => 'EDITAR_ACTUACION_CONTRATO',
                'descripcion_breve' => "Edición actuación en contrato #{$actuacion->actuable->id}",
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
        if ($actuacion->actuable_type !== Contrato::class || !$user || !in_array($user->tipo_usuario, ['admin', 'gestor', 'abogado'])) {
            abort(403, 'No autorizado para eliminar esta actuación.');
        }

        $contratoId = $actuacion->actuable_id; // Guardar ID antes de borrar
        $actuacion->delete();

        // ✅ AUDITORÍA GLOBAL
        AuditoriaEvento::create([
            'user_id' => Auth::id(),
            'evento' => 'ELIMINAR_ACTUACION_CONTRATO',
            'descripcion_breve' => "Eliminada actuación del contrato #{$contratoId}",
            'criticidad' => 'media',
            'direccion_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return back(303)->with('success', 'Actuación eliminada.'); 
    }
}