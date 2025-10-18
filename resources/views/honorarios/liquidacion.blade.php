@php
    // --- PREPARACIÓN DE DATOS (Lógica sin cambios) ---
    $clienteNombre = $cliente->nombre ?? 'ID ' . $contrato->cliente_id;

    // 1. Crear una colección unificada de todas las transacciones (pagos y cargos pagados)
    $transacciones = collect();

    // 2. Añadir los pagos normales
    foreach ($pagos as $pago) {
        $transacciones->push([
            'fecha' => \Illuminate\Support\Carbon::parse($pago->fecha),
            'concepto' => $pago->cuota_id ? 'Pago Cuota #'.$pago->cuota_numero : 'Abono General',
            'valor' => $pago->valor,
            'tipo' => 'PAGO',
        ]);
    }

    // 3. Añadir los cargos que ya han sido pagados
    foreach ($cargosPagados as $cargo) {
        $conceptoCargo = '';
        $tipoCargo = '';
        switch ($cargo->tipo) {
            case 'LITIS':
                $conceptoCargo = 'Honorarios por Resultado Litis';
                $tipoCargo = 'LITIS';
                break;
            case 'GASTO':
                $conceptoCargo = 'Reembolso Gasto: ' . $cargo->descripcion;
                $tipoCargo = 'GASTO';
                break;
            default:
                $conceptoCargo = 'Cargo Adicional: ' . $cargo->descripcion;
                $tipoCargo = 'CARGO';
                break;
        }

        $transacciones->push([
            'fecha' => \Illuminate\Support\Carbon::parse($cargo->fecha_pago_cargo),
            'concepto' => $conceptoCargo,
            'valor' => $cargo->monto,
            'tipo' => $tipoCargo,
        ]);
    }

    // 4. Ordenar todas las transacciones por fecha
    $transacciones = $transacciones->sortBy('fecha');

    // 5. Calcular totales para el resumen final
    $totalPagado = $transacciones->sum('valor');
    $deudaTotal = $contrato->monto_total + $totalCargosValor;
    $saldoPendiente = $deudaTotal - $totalPagado;

@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estado de Cuenta - Contrato #{{ $contrato->id }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 p-4 md:p-8">

    <div class="max-w-4xl mx-auto bg-white p-8 md:p-12 rounded-lg shadow-md border border-gray-200">

        <!-- SECCIÓN 1: Encabezado con Logo e Información del Documento -->
        <header class="flex justify-between items-start pb-8 border-b border-gray-200">
            <!-- Logo y Nombre de la Empresa -->
            <div class="flex items-center gap-4">
                <svg class="h-10 w-10 text-slate-800" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path>
                </svg>
                <div>
                    <h2 class="text-xl font-bold text-slate-800">Abogados en Colombia</h2>
                    <p class="text-sm text-gray-500">Asesoría Legal y Financiera</p>
                </div>
            </div>
            <!-- Información del Estado de Cuenta -->
            <div class="text-right">
                <h1 class="text-3xl font-bold text-slate-900">Estado de Cuenta</h1>
                <p class="text-sm text-gray-500 mt-1">Contrato de Honorarios #{{ $contrato->id }}</p>
                <p class="text-sm text-gray-500 mt-2"><strong>Fecha de Emisión:</strong> {{ \Illuminate\Support\Carbon::now()->translatedFormat('d F Y') }}</p>
            </div>
        </header>

        <!-- SECCIÓN 2: Información del Cliente y Contrato -->
        <div class="flex flex-row gap-6 my-8">
            <div class="flex-1 bg-gray-50 p-4 rounded-lg border border-gray-200">
                <p class="text-xs text-gray-500 font-semibold uppercase">Cliente</p>
                <p class="text-lg font-medium text-gray-800">{{ $clienteNombre }}</p>
            </div>
            <div class="flex-1 bg-gray-50 p-4 rounded-lg border border-gray-200">
                <p class="text-xs text-gray-500 font-semibold uppercase">Modalidad del Contrato</p>
                <p class="text-lg font-medium text-gray-800">{{ str_replace('_', ' ', $contrato->modalidad) }}</p>
            </div>
        </div>


        <!-- SECCIÓN 3: Historial de Transacciones Pagadas -->
        <div class="mb-10">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Historial de Transacciones Pagadas</h2>
            <div class="overflow-x-auto border border-gray-200 rounded-lg">
                <table class="min-w-full">
                    <thead class="bg-gray-100">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider" style="width: 20%;">Fecha</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider" style="width: 55%;">Concepto</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider" style="width: 25%;">Valor</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($transacciones as $item)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $item['fecha']->format('d/m/Y') }}</td>
                                <td class="px-6 py-4 text-sm text-gray-800">
                                    <span>{{ $item['concepto'] }}</span>
                                    <!-- Etiquetas de tipo de transacción -->
                                    @if($item['tipo'] === 'PAGO')
                                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">PAGO</span>
                                    @elseif($item['tipo'] === 'GASTO')
                                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">GASTO</span>
                                    @elseif($item['tipo'] === 'LITIS')
                                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">LITIS</span>
                                    @else
                                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">CARGO</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900 text-right">$ {{ number_format($item['valor'], 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-10 text-center text-sm text-gray-500">No se han registrado transacciones pagadas hasta la fecha.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- SECCIÓN 4: Detalle de Cuotas Pendientes -->
        @if(!$cuotasPendientes->isEmpty())
            <div class="mb-12">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Detalle de Cuotas Pendientes</h2>
                <div class="overflow-x-auto border border-gray-200 rounded-lg">
                    <table class="min-w-full">
                        <thead class="bg-gray-100">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider">Cuota N°</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Fecha Venc.</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider">Valor Cuota</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider">Mora Acum.</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider">Total a Pagar</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($cuotasPendientes as $cuota)
                                @php
                                    $mora = $cuota->intereses_mora_acumulados ?? 0;
                                    $totalCuota = $cuota->valor + $mora;
                                @endphp
                                <tr>
                                    <td class="px-6 py-5 whitespace-nowrap text-sm font-medium text-gray-800 text-center">{{ $cuota->numero }}</td>
                                    <td class="px-6 py-5 whitespace-nowrap text-sm text-gray-600">{{ \Illuminate\Support\Carbon::parse($cuota->fecha_vencimiento)->format('d/m/Y') }}</td>
                                    <td class="px-6 py-5 whitespace-nowrap text-sm text-gray-800 text-right">$ {{ number_format($cuota->valor, 0, ',', '.') }}</td>
                                    <td class="px-6 py-5 whitespace-nowrap text-sm text-red-600 text-right">$ {{ number_format($mora, 0, ',', '.') }}</td>
                                    <td class="px-6 py-5 whitespace-nowrap text-sm font-semibold text-gray-900 text-right">$ {{ number_format($totalCuota, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-100 border-t-2 border-gray-300">
                            <tr>
                                <td colspan="4" class="px-6 py-3 text-right text-sm font-bold text-gray-700 uppercase">Total Pendiente en Cuotas</td>
                                <td class="px-6 py-3 text-right text-base font-bold text-gray-900">
                                    $ {{ number_format($cuotasPendientes->sum(fn($c) => $c->valor + ($c->intereses_mora_acumulados ?? 0)), 0, ',', '.') }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @endif

        <!-- SECCIÓN 5: Resumen Financiero General -->
        <div class="mt-8 pt-8 border-t-2 border-dashed border-gray-300">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-start">
                <!-- Columna para notas o información adicional (Opcional) -->
                <div>
                     <h3 class="text-base font-semibold text-gray-800">Notas Adicionales</h3>
                     <p class="text-xs text-gray-500 mt-2">
                        Este es un resumen del estado de su contrato a la fecha de emisión. Para cualquier consulta, no dude en contactarnos. Los pagos pueden tardar hasta 48 horas en verse reflejados.
                     </p>
                </div>
                <!-- Columna para el resumen de totales -->
                <div class="space-y-3">
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-600">Monto Fijo Contrato:</span>
                        <span class="font-medium text-gray-900">$ {{ number_format($contrato->monto_total, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-600">(+) Total Cargos Adicionales:</span>
                        <span class="font-medium text-gray-900">$ {{ number_format($totalCargosValor, 0, ',', '.') }}</span>
                    </div>
                     <div class="flex justify-between items-center text-sm pt-2 border-t border-gray-200">
                        <span class="font-semibold text-gray-700">Deuda Total:</span>
                        <span class="font-bold text-gray-900">$ {{ number_format($deudaTotal, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between items-center text-sm text-emerald-600">
                        <span class="font-medium">(-) Total Pagado:</span>
                        <span class="font-medium">-$ {{ number_format($totalPagado, 0, ',', '.') }}</span>
                    </div>
                    <!-- Saldo Pendiente Total - Más destacado -->
                    <div class="flex justify-between items-center p-4 bg-red-50 border-red-200 border rounded-lg mt-4">
                        <span class="text-lg font-bold text-red-700">Saldo Pendiente Total:</span>
                        <span class="text-2xl font-bold text-red-700">$ {{ number_format($saldoPendiente, 0, ',', '.') }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECCIÓN 6: Pie de Página -->
        <footer class="text-center text-xs text-gray-400 mt-12 pt-6 border-t border-gray-200">
            <p>Gracias por su confianza.</p>
            <p>Abogados en Colombia S.A.S.| Calle 44A #68A - 106, Medellín | 315 281 9233 | abogadosencolombiasas@gmail.com</p>
        </footer>
    </div>
</body>
</html>

