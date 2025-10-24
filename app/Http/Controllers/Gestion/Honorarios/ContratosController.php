<?php

namespace App\Http\Controllers\Gestion\Honorarios;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth; // <-- Importado
use Inertia\Inertia;
use Carbon\Carbon;
// --- INICIO: Añadir modelos ---
use App\Models\Contrato;
use App\Models\Persona;
use App\Models\Actuacion; // <-- Importado
// --- FIN: Añadir modelos ---

class ContratosController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string)$request->input('q',''));
        $estado = trim((string)$request->input('estado',''));
        $stats = ['activeValue'=>0,'activeCount'=>0,'closedCount'=>0];

        // --- INICIO: Revertir withCount a subquery DB::raw ---
        $contratosQuery = Contrato::query()
            ->where('estado', '!=', 'REESTRUCTURADO')
            ->with('cliente:id,nombre_completo') // Cargar relación cliente
            ->addSelect(['*', DB::raw('(SELECT SUM(valor) FROM contrato_pagos WHERE contrato_pagos.contrato_id = contratos.id) as total_pagado')]); // Subquery para total_pagado
        // --- FIN: Revertir withCount a subquery DB::raw ---

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
            ->through(fn ($contrato) => [ // Transformar para la vista si es necesario
                'id' => $contrato->id,
                'cliente_id' => $contrato->cliente_id,
                'persona_nombre' => $contrato->cliente?->nombre_completo,
                'estado' => $contrato->estado,
                'monto_total' => $contrato->monto_total,
                'total_pagado' => $contrato->total_pagado ?? 0, // Usar el alias de la subquery
                'created_at' => $contrato->created_at,
                'modalidad' => $contrato->modalidad, // Asegúrate de incluir todos los campos necesarios para Index.vue
                // Añadir otros campos que necesite la vista Index
            ]);

        // --- Stats usando Modelo ---
        try {
            $active = Contrato::whereIn('estado',['ACTIVO', 'PAGOS_PENDIENTES', 'EN_MORA']);
            $stats['activeCount'] = (clone $active)->count();
            $stats['activeValue'] = (clone $active)->sum('monto_total');
            $stats['closedCount'] = Contrato::where('estado','CERRADO')->count();
        } catch (\Throwable $e) {
            // Manejar posible error si la tabla no existe o algo falla
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
            // --- Usando Modelo ---
            $plantilla = Contrato::find($request->input('from'));
        }

        $personas = [];
        $modalidades = ['CUOTAS','PAGO_UNICO','LITIS','CUOTA_MIXTA'];

        try {
            // --- Usando Modelo Persona si existe ---
            if (class_exists(Persona::class)) {
                $personas = Persona::select('id','nombre_completo as nombre')
                                        ->orderBy('nombre_completo')
                                        ->limit(500) // Considerar si este límite sigue siendo necesario
                                        ->get();
            } else {
                // Fallback a DB::table si el modelo Persona no existe
                $personas = DB::table('personas')
                    ->select('id','nombre_completo as nombre')
                    ->orderBy('nombre_completo')
                    ->limit(500)
                    ->get();
            }
        } catch (\Throwable $e) {}

        return Inertia::render('Gestion/Honorarios/Contratos/Create', [
            'clientes'    => $personas,
            'modalidades' => $modalidades,
            'plantilla'   => $plantilla ? $plantilla->toArray() : null, // Convertir a array para props
        ]);
    }

    public function store(Request $request)
    {
        $rules = [
            'cliente_id' => ['required', 'exists:personas,id'], // Asegurar existencia del cliente
            'modalidad'  => ['required', 'in:CUOTAS,PAGO_UNICO,LITIS,CUOTA_MIXTA'],
            'inicio'     => ['required', 'date'],
            'anticipo'   => ['nullable', 'numeric', 'min:0'],
            'nota'       => ['nullable', 'string'],
            'contrato_origen_id' => ['nullable', 'integer', 'exists:contratos,id'],
        ];

        $modalidad = $request->input('modalidad');

        if ($modalidad === 'CUOTAS' || $modalidad === 'PAGO_UNICO' || $modalidad === 'CUOTA_MIXTA') {
            $rules['monto_total'] = ['required', 'numeric', 'min:0'];
            $rules['cuotas']      = ['required', 'integer', 'min:1', 'max:120'];
        }

        if ($modalidad === 'LITIS' || $modalidad === 'CUOTA_MIXTA') {
            $rules['porcentaje_litis'] = ['required', 'numeric', 'min:0', 'max:100'];
        }

        $validatedData = Validator::make($request->all(), $rules, [], ['cliente_id' => 'cliente'])->validate();

        $monto_total = round((float)($validatedData['monto_total'] ?? 0), 2);
        $anticipo    = round((float)($validatedData['anticipo'] ?? 0), 2);

        if ($anticipo > $monto_total && in_array($modalidad, ['CUOTAS', 'PAGO_UNICO', 'CUOTA_MIXTA'])) {
            return back()->withErrors(['anticipo' => 'El anticipo no puede superar el monto total.'])->withInput();
        }

        $contrato = null;

        DB::transaction(function () use (&$contrato, $validatedData, $monto_total, $anticipo, $modalidad) {
            $contrato_origen_id = $validatedData['contrato_origen_id'] ?? null;

            if ($contrato_origen_id) {
                Contrato::where('id', $contrato_origen_id)->update(['estado' => 'REESTRUCTURADO']);
            }

            // --- Creación usando Modelo ---
            $contrato = Contrato::create([
                'cliente_id'         => $validatedData['cliente_id'],
                'monto_total'        => $monto_total,
                'anticipo'           => $anticipo,
                'porcentaje_litis'   => $validatedData['porcentaje_litis'] ?? null,
                'monto_base_litis'   => null,
                'modalidad'          => $modalidad,
                'estado'             => 'ACTIVO',
                'inicio'             => $validatedData['inicio'],
                'nota'               => $validatedData['nota'] ?? null,
                'contrato_origen_id' => $contrato_origen_id,
            ]);

            if ($modalidad === 'LITIS') {
                return;
            }

            $neto = max(0, $monto_total - $anticipo);
            $cuotasCount = (int)($validatedData['cuotas'] ?? 1);

            if ($modalidad === 'PAGO_UNICO') {
                // Asumiendo que existe el modelo ContratoCuota
                // Si no, mantener DB::table('contrato_cuotas')->insert(...)
                DB::table('contrato_cuotas')->insert([
                    'contrato_id'       => $contrato->id,
                    'numero'            => 1,
                    'fecha_vencimiento' => $validatedData['inicio'],
                    'valor'             => $neto,
                    'estado'            => $neto > 0 ? 'PENDIENTE' : 'PAGADA',
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
            } elseif ($modalidad === 'CUOTAS' || $modalidad === 'CUOTA_MIXTA') {
                if ($cuotasCount < 1) $cuotasCount = 1;
                $netoCents  = (int) round($neto * 100);
                $baseCents  = intdiv($netoCents, $cuotasCount);
                $restoCents = $netoCents - ($baseCents * $cuotasCount);

                $cuotasData = [];
                for ($i = 1; $i <= $cuotasCount; $i++) {
                    $valorCents = $baseCents + ($i <= $restoCents ? 1 : 0);
                    $valor      = $valorCents / 100.0;
                    $fecha      = Carbon::parse($validatedData['inicio'])->addMonthsNoOverflow($i - 1)->toDateString();

                    $cuotasData[] = [
                        'contrato_id'       => $contrato->id,
                        'numero'            => $i,
                        'fecha_vencimiento' => $fecha,
                        'valor'             => $valor,
                        'estado'            => $valor > 0 ? 'PENDIENTE' : 'PAGADA',
                        'created_at'        => now(),
                        'updated_at'        => now(),
                    ];
                }
                // Insertar en bloque
                DB::table('contrato_cuotas')->insert($cuotasData);
            }
        });

        return redirect()->route('honorarios.contratos.show', $contrato->id)->with('success', 'Contrato creado.');
    }

    public function show($id)
    {
        // --- Usando Modelo con relaciones precargadas ---
        $contrato = Contrato::with([
                'cliente:id,nombre_completo', // <-- CORREGIDO: Cargar nombre_completo
                'contratoOrigen:id,estado,monto_total,modalidad',
                'actuaciones' => function ($query) {
                    $query->with('user:id,name')->orderBy('created_at', 'desc');
                }
            ])
            ->findOrFail($id);

        $contratoOrigen = $contrato->contratoOrigen;
        $cliente = $contrato->cliente;
        $actuaciones = $contrato->actuaciones;

        $clientes = [];
        $modalidades = ['CUOTAS','PAGO_UNICO','LITIS','CUOTA_MIXTA'];

        try {
            if (class_exists(Persona::class)) {
                $clientes = Persona::select('id','nombre_completo as nombre')
                                        ->orderBy('nombre_completo')
                                        ->limit(500)
                                        ->get();
            } else {
                $clientes = DB::table('personas')->select('id','nombre_completo as nombre')->orderBy('nombre_completo')->limit(500)->get();
            }
        } catch (\Throwable $e) {}

        // --- Manteniendo DB::table para cuotas, cargos, pagos hasta confirmar modelos ---
        $total_cargos_valor = DB::table('contrato_cargos')->where('contrato_id', $id)->sum('monto');
        $total_pagos_valor  = DB::table('contrato_pagos')->where('contrato_id', $id)->sum('valor');

        $cuotas = DB::table('contrato_cuotas')
            ->where('contrato_id', $id)
            ->orderBy('numero')
            ->paginate(15, ['*'], 'cuotasPage');

        $cargos = DB::table('contrato_cargos as c')
            ->leftJoin('contrato_pagos as p', 'c.pago_id', '=', 'p.id')
            ->where('c.contrato_id', $id)
            ->select('c.*', 'p.fecha as fecha_pago_cargo', 'p.metodo as metodo_pago_cargo', 'p.nota as nota_pago_cargo', 'p.comprobante as comprobante_pago_cargo')
            ->orderByDesc('c.fecha_aplicado')
            ->paginate(15, ['*'], 'cargosPage');

        $pagos = DB::table('contrato_pagos')
            ->where('contrato_id', $id)
            ->orderByDesc('fecha')->orderByDesc('id')
            ->paginate(15, ['*'], 'pagosPage');

        // --- INICIO: CORRECCIÓN - Pasar objetos Eloquent directamente ---
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
        // --- FIN: CORRECCIÓN ---
    }

    public function reestructurar($id)
    {
        // --- Usando Modelo ---
        $contrato = Contrato::find($id);
        if (!$contrato) {
            return redirect()->route('honorarios.contratos.index')->with('error', 'Contrato no encontrado.');
        }

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

        $contrato = Contrato::findOrFail($id); // --- Usando Modelo ---

        // --- Manteniendo DB::table para cuota hasta confirmar modelo ---
        $cuota = DB::table('contrato_cuotas')
            ->where('id', $validated['cuota_id'])
            ->where('contrato_id', $id)
            ->first();
        if (!$cuota) return back()->withErrors(['cuota_id'=>'Cuota no encontrada.']);

        $valor = round((float)$validated['valor'], 2);
        // Incluir lógica de mora si es necesario aquí antes de comparar
        $valorMinimo = (float)$cuota->valor; // + $cuota->intereses_mora_acumulados ?? 0;
        if ($valor + 0.01 < $valorMinimo) {
            return back()->withErrors(['valor'=>'Solo se admiten pagos iguales o superiores al valor pendiente de la cuota (incluyendo mora).']);
        }

        $path = null;
        if ($request->hasFile('comprobante')) {
            $path = $request->file('comprobante')->store("comprobantes/pagos/{$id}", 'public');
        }

        DB::transaction(function () use ($id, $cuota, $validated, $valor, $path) {
            // --- Manteniendo DB::table para pago hasta confirmar modelo ---
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

            DB::table('contrato_cuotas')->where('id',$cuota->id)->update([
                'estado'     => 'PAGADA',
                'fecha_pago' => $validated['fecha'],
                'updated_at' => now(),
            ]);

            $this->checkAndCloseContract($id);
        });

        return back()->with('success','Pago registrado.');
    }

    public function resolverLitis($id, Request $request)
    {
        $validated = Validator::make($request->all(), [
            'monto_base_litis' => ['required', 'numeric', 'min:0'],
            'fecha_inicio_intereses' => ['nullable', 'date'], // Añadido
        ])->validate();

        // --- Usando Modelo ---
        $contrato = Contrato::findOrFail($id);

        if ($contrato->modalidad !== 'LITIS' && $contrato->modalidad !== 'CUOTA_MIXTA') {
            return back()->with('error', 'Esta acción solo es válida para contratos de tipo Litis o Cuota Mixta.');
        }

        $monto_base = round((float)$validated['monto_base_litis'], 2);
        $porcentaje = (float)$contrato->porcentaje_litis;
        $honorarios = round(($monto_base * $porcentaje) / 100, 2);

        DB::transaction(function () use ($contrato, $monto_base, $honorarios, $validated) {
            $contrato->update(['monto_base_litis' => $monto_base]);

            // --- Manteniendo DB::table para cargo hasta confirmar modelo ---
            DB::table('contrato_cargos')->insert([
                'contrato_id'            => $contrato->id,
                'tipo'                   => 'LITIS',
                'monto'                  => $honorarios,
                'estado'                 => 'PENDIENTE',
                'descripcion'            => "Honorarios del {$contrato->porcentaje_litis}% sobre un monto base de {$monto_base}.",
                'fecha_aplicado'         => now()->toDateString(),
                'fecha_inicio_intereses' => $validated['fecha_inicio_intereses'] ?? null, // Añadido
                'created_at'             => now(),
                'updated_at'             => now(),
            ]);
            // Forzar estado a Pagos Pendientes al resolver Litis si hay honorarios
             if ($honorarios > 0 && $contrato->estado !== 'PAGOS_PENDIENTES') {
                 $contrato->update(['estado' => 'PAGOS_PENDIENTES']);
             }
        });


        return back()->with('success', 'Resultado del caso registrado. Se ha generado el cargo por honorarios.');
    }

    public function pagarCargo($contrato_id, Request $request) // Cambiado $id a $contrato_id por claridad
    {
        $validated = Validator::make($request->all(), [
            'cargo_id'    => ['required','integer'],
            'valor'       => ['required','numeric','min:0.01'],
            'fecha'       => ['required','date'],
            'metodo'      => ['required','in:EFECTIVO,TRANSFERENCIA,TARJETA,OTRO'],
            'nota'        => ['nullable','string','max:1000'],
            'comprobante' => ['required','file','mimes:pdf,jpg,jpeg,png,webp','max:5120'],
        ])->validate();

        $contrato = Contrato::findOrFail($contrato_id); // --- Usando Modelo ---

        // --- Manteniendo DB::table para cargo hasta confirmar modelo ---
        $cargo = DB::table('contrato_cargos')
            ->where('id', $validated['cargo_id'])
            ->where('contrato_id', $contrato_id)
            ->first();
        if (!$cargo) return back()->withErrors(['cargo_id'=>'Cargo no encontrado.']);

        $valor = round((float)$validated['valor'], 2);
        // Incluir lógica de mora si es necesario
        $valorMinimo = (float)$cargo->monto; // + $cargo->intereses_mora_acumulados ?? 0;
        if ($valor + 0.01 < $valorMinimo) {
            return back()->withErrors(['valor'=>'El pago debe ser igual o superior al valor pendiente del cargo (incluyendo mora).']);
        }

        $path = $request->file('comprobante')->store("comprobantes/cargos_pagos/{$contrato_id}", 'public');

        DB::transaction(function () use ($contrato_id, $cargo, $validated, $valor, $path) {
            // --- Manteniendo DB::table para pago hasta confirmar modelo ---
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
                'fecha_pago' => $validated['fecha'], // Fecha de pago del cargo
                'pago_id'    => $pagoId, // ID del registro en contrato_pagos
                'updated_at' => now(),
            ]);

            $this->checkAndCloseContract($contrato_id);
        });

        return back()->with('success','Pago de cargo registrado.');
    }

    public function agregarCargo($id, Request $request)
    {
        $validated = Validator::make($request->all(), [
            'monto'                  => ['required','numeric','min:0.01'],
            'descripcion'            => ['required','string','max:255'],
            'fecha'                  => ['required','date'], // Cambiado a 'fecha' para consistencia
            'comprobante'            => ['nullable','file','mimes:pdf,jpg,jpeg,png,webp','max:5120'],
            'fecha_inicio_intereses' => ['nullable', 'date', 'after_or_equal:fecha'],
        ])->validate();

        $contrato = Contrato::findOrFail($id); // --- Usando Modelo ---

        $path = null;
        if ($request->hasFile('comprobante')) {
            $path = $request->file('comprobante')->store("comprobantes/cargos/{$id}", 'public');
        }

        // --- Manteniendo DB::table para cargo hasta confirmar modelo ---
        DB::table('contrato_cargos')->insert([
            'contrato_id'            => $id,
            'tipo'                   => 'GASTO', // O el tipo correspondiente
            'monto'                  => $validated['monto'],
            'estado'                 => 'PENDIENTE',
            'descripcion'            => $validated['descripcion'],
            'comprobante'            => $path,
            'fecha_aplicado'         => $validated['fecha'], // Usar 'fecha' validada
            'fecha_inicio_intereses' => $validated['fecha_inicio_intereses'] ?? null,
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        // Opcional: Actualizar estado del contrato si aplica (Ej: si estaba ACTIVO y se añade un GASTO, pasa a PAGOS_PENDIENTES)
        if ($contrato->estado === 'ACTIVO') {
             $contrato->update(['estado' => 'PAGOS_PENDIENTES']);
        }


        return back()->with('success','Cargo añadido.');
    }

    public function activar($id)
    {
        $contrato = Contrato::findOrFail($id); // --- Usando Modelo ---
        $contrato->update(['estado'=>'ACTIVO']);
        return back()->with('success','Contrato activado.');
    }

    public function cerrar($id, Request $request)
    {
        $validated = Validator::make($request->all(), [
            'monto'                  => ['nullable','numeric','min:0'],
            'descripcion'            => ['required_with:monto','nullable','string','max:255'], // Descripción requerida si hay monto
            'fecha_inicio_intereses' => ['nullable', 'date'],
        ])->validate();

        DB::transaction(function () use ($id, $validated) {
            $monto = $validated['monto'] ?? null;
            $descripcion = $validated['descripcion'] ?? null;

            if ($monto !== null && $monto > 0) {
                DB::table('contrato_cargos')->insert([
                    'contrato_id'            => $id,
                    'tipo'                   => 'CIERRE_ATIPICO', // O el tipo adecuado
                    'monto'                  => $monto,
                    'estado'                 => 'PENDIENTE',
                    'descripcion'            => $descripcion ?: 'Cargo por cierre manual.',
                    'fecha_aplicado'         => now()->toDateString(),
                    'fecha_inicio_intereses' => $validated['fecha_inicio_intereses'] ?? null,
                    'created_at'             => now(),
                    'updated_at'             => now(),
                ]);
            }

            $this->checkAndCloseContract($id, true); // Pasar true para forzar estado PAGOS_PENDIENTES si algo queda
        });

        return back()->with('success','Contrato actualizado.');
    }

    public function saldarContrato($id)
    {
        $contrato = Contrato::findOrFail($id); // --- Usando Modelo ---
        // Añadir validación: Solo saldar si está en PAGOS_PENDIENTES y saldo es 0?
        $contrato->update(['estado'=>'CERRADO']);
        return back()->with('success','Contrato saldado y cerrado.');
    }

    public function reabrir($id)
    {
        $contrato = Contrato::findOrFail($id); // --- Usando Modelo ---
        $contrato->update(['estado'=>'ACTIVO']);
        return back()->with('success','Contrato reabierto y activado.');
    }

    // --- MANTENER DB::table HASTA CONFIRMAR MODELOS ---
    public function verComprobante($pago_id)
    {
        $pago = DB::table('contrato_pagos')->find($pago_id);
        if (!$pago || !$pago->comprobante) abort(404,'Comprobante no encontrado.');

        if (!Storage::disk('public')->exists($pago->comprobante)) abort(404,'Archivo no encontrado.');

        return Storage::disk('public')->response($pago->comprobante);
    }

    // --- MANTENER DB::table HASTA CONFIRMAR MODELOS ---
    public function verCargoComprobante($cargo_id)
    {
        $cargo = DB::table('contrato_cargos')->find($cargo_id);
        if (!$cargo || !$cargo->comprobante) abort(404,'Comprobante no encontrado.');

        if (!Storage::disk('public')->exists($cargo->comprobante)) abort(404,'Archivo no encontrado.');

        return Storage::disk('public')->response($cargo->comprobante);
    }

    // --- Refactorizado para usar Modelo ---
    public function pdfContrato($id)
    {
        $contrato = Contrato::with('cliente:id,nombre_completo')->findOrFail($id);
        $cliente = $contrato->cliente;
        $cuotas = DB::table('contrato_cuotas')->where('contrato_id', $id)->orderBy('numero')->get(); // Mantener hasta confirmar modelo

        $data = [
            'contrato' => $contrato->toArray(),
            'cliente' => $cliente ? $cliente->toArray() : null,
            'cuotas' => $cuotas->toArray()
        ];

        // Asumiendo que Barryvdh\DomPDF está instalado y configurado
        if (class_exists('\\Barryvdh\\DomPDF\\Facade\\Pdf')) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('honorarios.contrato', $data); // Asegúrate que la vista exista
            return $pdf->stream("contrato_{$id}.pdf");
        }
        // Fallback si PDF no está disponible (o para debug)
        return response()->view('honorarios.contrato', $data);
    }

    // --- Refactorizado para usar Modelo ---
    public function pdfLiquidacion($id)
    {
        $contrato = Contrato::with('cliente:id,nombre_completo')->findOrFail($id);
        $cliente = $contrato->cliente;

        // --- Mantener DB::table hasta confirmar modelos ---
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

        $totalCargosValor = DB::table('contrato_cargos')
            ->where('contrato_id', $id)
            ->sum('monto'); // Considerar sumar intereses de mora si aplica

        $cuotasPendientes = DB::table('contrato_cuotas')
            ->where('contrato_id', $id)
            ->where('estado', 'PENDIENTE') // O estado != 'PAGADA'
            ->orderBy('numero')
            ->get();

        $data = [
            'contrato' => $contrato->toArray(),
            'cliente' => $cliente ? $cliente->toArray() : null,
            'pagos' => $pagos->toArray(),
            'cargosPagados' => $cargosPagados->toArray(),
            'totalCargosValor' => $totalCargosValor,
            'cuotasPendientes' => $cuotasPendientes->toArray()
        ];

        if (class_exists('\\Barryvdh\\DomPDF\\Facade\\Pdf')) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('honorarios.liquidacion', $data); // Asegúrate que la vista exista
            return $pdf->stream("liquidacion_{$id}.pdf");
        }

        return response()->view('honorarios.liquidacion', $data);
    }

    // --- Refactorizado para usar Modelo ---
    private function checkAndCloseContract($contrato_id, $isManualClosure = false)
    {
        // --- Mantener DB::table hasta confirmar modelos ---
        $cuotasPendientes = DB::table('contrato_cuotas')->where('contrato_id',$contrato_id)->where('estado','!=','PAGADA')->count();
        $cargosPendientes = DB::table('contrato_cargos')->where('contrato_id',$contrato_id)->where('estado','!=','PAGADO')->count();

        $contrato = Contrato::find($contrato_id);
        if (!$contrato) return; // Contrato no encontrado

        if ($cuotasPendientes === 0 && $cargosPendientes === 0) {
            if ($contrato->estado !== 'CERRADO') {
                $contrato->update(['estado'=>'CERRADO']);
            }
        } elseif ($isManualClosure) {
            if ($contrato->estado !== 'PAGOS_PENDIENTES') {
                $contrato->update(['estado'=>'PAGOS_PENDIENTES']);
            }
        }
        // Si no es cierre manual y quedan pendientes, el estado no cambia (se mantiene ACTIVO o EN_MORA etc.)
    }


    // --- Refactorizado para usar Modelo ---
    public function subirDocumento($id, Request $request)
    {
        $validated = Validator::make($request->all(), [
            'documento' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ])->validate();

        $contrato = Contrato::findOrFail($id);

        DB::transaction(function () use ($contrato, $request, $validated) {
            // Eliminar documento anterior si existe
            if ($contrato->documento_contrato) {
                Storage::disk('public')->delete($contrato->documento_contrato);
            }

            // Subir nuevo documento
            $path = $request->file('documento')->store("documentos/contratos/{$contrato->id}", 'public');

            // Actualizar registro
            $contrato->update(['documento_contrato' => $path]);
        });

        return back()->with('success', 'Documento del contrato subido correctamente.');
    }

    /**
     * Ver/descargar documento del contrato
     */
    // --- Refactorizado para usar Modelo ---
    public function verDocumento($id)
    {
        $contrato = Contrato::findOrFail($id);
        if (!$contrato->documento_contrato) {
            abort(404, 'Documento no encontrado.');
        }

        if (!Storage::disk('public')->exists($contrato->documento_contrato)) {
            // Opcional: intentar limpiar la referencia si el archivo no existe?
            // $contrato->update(['documento_contrato' => null]);
            abort(404, 'Archivo no encontrado en el disco.');
        }

        return Storage::disk('public')->response($contrato->documento_contrato);
    }

    /**
     * Eliminar documento del contrato
     */
    // --- Refactorizado para usar Modelo ---
    public function eliminarDocumento($id)
    {
        $contrato = Contrato::findOrFail($id);

        if (!$contrato->documento_contrato) {
            return back()->with('info', 'No hay documento para eliminar.'); // Usar 'info' o 'warning'
        }

        DB::transaction(function () use ($contrato) {
            // Eliminar archivo del storage
            Storage::disk('public')->delete($contrato->documento_contrato);

            // Actualizar registro
            $contrato->update(['documento_contrato' => null]);
        });

        return back()->with('success', 'Documento eliminado correctamente.');
    }

    /**
     * Eliminar permanentemente un contrato y sus relaciones.
     */
    // --- Refactorizado para usar Modelo ---
    public function destroy($id)
    {
        $contrato = Contrato::findOrFail($id);

        DB::transaction(function () use ($contrato) {
            // 1. Eliminar archivo del documento si existe
            if ($contrato->documento_contrato) {
                Storage::disk('public')->delete($contrato->documento_contrato);
            }

            // 2. Eliminar registros relacionados (usando DB::table hasta confirmar modelos)
            DB::table('contrato_cuotas')->where('contrato_id', $contrato->id)->delete();
            DB::table('contrato_cargos')->where('contrato_id', $contrato->id)->delete();
            DB::table('contrato_pagos')->where('contrato_id', $contrato->id)->delete();
            // --- INICIO: Eliminar actuaciones asociadas ---
            // Nota: Usar Actuacion::class requiere importar la clase
            Actuacion::where('actuable_type', Contrato::class)->where('actuable_id', $contrato->id)->delete();
            // --- FIN: Eliminar actuaciones asociadas ---

            // 3. Eliminar el contrato principal
            $contrato->delete();

            // Opcional: ¿Qué hacer con los contratos REESTRUCTURADOS que apuntaban a este?
            // Contrato::where('contrato_origen_id', $contrato->id)->update(['contrato_origen_id' => null]);
        });

        return redirect()->route('honorarios.contratos.index')->with('success', "Contrato #{$id} eliminado permanentemente.");
    }

    /**
     * Guarda una nueva actuación manual para un contrato.
     */
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
            'user_id' => Auth::id(), // Asigna el usuario autenticado
        ]);

        return back()->with('success', 'Actuación registrada.');
    }

    // --- INICIO: Métodos CRUD para Actuaciones ---
    /**
     * Actualiza una actuación específica.
     */
    public function updateActuacion(Request $request, Actuacion $actuacion)
    {
        // --- INICIO: Autorización por Rol (Corregida) ---
        $user = Auth::user();
        if (!$user || !in_array($user->tipo_usuario, ['admin', 'gestor', 'abogado'])) {
             abort(403, 'No autorizado para editar esta actuación.');
        }
        // --- FIN: Autorización por Rol (Corregida) ---

        $validated = $request->validate([
            'nota' => ['required', 'string', 'max:5000'],
            'fecha_actuacion' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $actuacion->update($validated);

        return back(303)->with('success', 'Actuación actualizada.'); // Usar 303 para PUT/DELETE con Inertia
    }

    /**
     * Elimina una actuación específica.
     */
    public function destroyActuacion(Actuacion $actuacion)
    {
        // --- INICIO: Autorización por Rol (Corregida) ---
        $user = Auth::user();
        if (!$user || !in_array($user->tipo_usuario, ['admin', 'gestor', 'abogado'])) {
             abort(403, 'No autorizado para eliminar esta actuación.');
        }
        // --- FIN: Autorización por Rol (Corregida) ---

        $actuacion->delete();

        return back(303)->with('success', 'Actuación eliminada.'); // Usar 303 para PUT/DELETE con Inertia
    }
    // --- FIN: Métodos CRUD para Actuaciones ---
}

