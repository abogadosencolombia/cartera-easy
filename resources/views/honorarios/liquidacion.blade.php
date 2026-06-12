@php
    use Illuminate\Support\Carbon;

    $clienteNombre = $cliente->nombre_completo ?? 'ID ' . $contrato->cliente_id;
    $money = fn ($value) => '$ ' . number_format((float) ($value ?? 0), 0, ',', '.');
    $date = fn ($value) => $value ? Carbon::parse($value)->format('d/m/Y') : 'N/A';
    $modalidad = str_replace('_', ' ', (string) ($contrato->modalidad ?? ''));
    $estado = str_replace('_', ' ', (string) ($contrato->estado ?? ''));

    $transacciones = collect();

    foreach ($pagos as $pago) {
        if (!empty($pago->cargo_id)) {
            continue;
        }

        $transacciones->push([
            'fecha' => Carbon::parse($pago->fecha),
            'concepto' => $pago->cuota_id ? 'Pago cuota #' . $pago->cuota_numero : 'Abono general',
            'detalle' => $pago->nota ?? null,
            'valor' => $pago->valor,
            'tipo' => 'PAGO',
        ]);
    }

    foreach ($cargosPagados as $cargo) {
        $tipoCargo = match ($cargo->tipo) {
            'LITIS' => 'LITIS',
            'GASTO' => 'GASTO',
            default => 'CARGO',
        };

        $conceptoCargo = match ($cargo->tipo) {
            'LITIS' => 'Honorarios por resultado litis',
            'GASTO' => 'Reembolso de gasto',
            default => 'Cargo adicional',
        };

        $transacciones->push([
            'fecha' => Carbon::parse($cargo->fecha_pago_cargo),
            'concepto' => trim($conceptoCargo . ': ' . ($cargo->descripcion ?? '')),
            'detalle' => $cargo->nota_pago_cargo ?? null,
            'valor' => $cargo->valor_pago_cargo ?? ((float) ($cargo->monto ?? 0) + (float) ($cargo->intereses_mora_acumulados ?? 0)),
            'tipo' => $tipoCargo,
        ]);
    }

    $transacciones = $transacciones->sortBy('fecha');
    $saldoPositivo = (float) ($saldoPendiente ?? 0) > 0;
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Estado de cuenta - Contrato #{{ $contrato->id }}</title>
    <style>
        @page { margin: 26px 30px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #111827; font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; line-height: 1.45; background: #ffffff; }
        .document { width: 100%; }
        .topbar { border-bottom: 3px solid #111827; padding-bottom: 16px; margin-bottom: 18px; }
        .brand { width: 55%; vertical-align: top; }
        .doc-meta { width: 45%; text-align: right; vertical-align: top; }
        .brand-mark { display: inline-block; width: 34px; height: 34px; border-radius: 8px; background: #111827; color: #ffffff; text-align: center; line-height: 34px; font-weight: 800; font-size: 15px; margin-right: 10px; }
        .brand-name { font-size: 18px; font-weight: 800; margin: 0; }
        .muted { color: #6b7280; }
        .label { color: #6b7280; font-size: 9px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
        h1 { margin: 0; font-size: 25px; line-height: 1.1; }
        h2 { margin: 0 0 9px; font-size: 14px; text-transform: uppercase; letter-spacing: .05em; }
        .section { margin-top: 18px; page-break-inside: avoid; }
        .panel { border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; }
        .panel-head { background: #f9fafb; border-bottom: 1px solid #e5e7eb; padding: 10px 12px; }
        .panel-body { padding: 12px; }
        .info-table, .summary-table, .data-table { width: 100%; border-collapse: collapse; }
        .info-table td { width: 25%; padding: 10px 12px; border: 1px solid #e5e7eb; vertical-align: top; }
        .value { margin-top: 4px; font-size: 12px; font-weight: 800; color: #111827; }
        .summary-table td { width: 16.66%; padding: 10px; border: 1px solid #d1d5db; vertical-align: top; }
        .summary-table .amount { margin-top: 4px; font-size: 13px; font-weight: 800; }
        .summary-total { background: #111827; color: #ffffff; }
        .summary-total .label, .summary-total .amount { color: #ffffff; }
        .summary-paid { background: #ecfdf5; }
        .summary-paid .amount { color: #047857; }
        .summary-balance { background: {{ $saldoPositivo ? '#fef2f2' : '#ecfdf5' }}; }
        .summary-balance .amount { color: {{ $saldoPositivo ? '#b91c1c' : '#047857' }}; font-size: 16px; }
        .data-table th { background: #f3f4f6; color: #374151; font-size: 9px; letter-spacing: .05em; text-transform: uppercase; padding: 8px; border-bottom: 1px solid #d1d5db; text-align: left; }
        .data-table td { padding: 9px 8px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .data-table tr:last-child td { border-bottom: 0; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .strong { font-weight: 800; }
        .danger { color: #b91c1c; }
        .success { color: #047857; }
        .badge { display: inline-block; border-radius: 999px; padding: 2px 7px; font-size: 8px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; }
        .badge-pago { background: #d1fae5; color: #047857; }
        .badge-gasto { background: #dbeafe; color: #1d4ed8; }
        .badge-litis { background: #ede9fe; color: #6d28d9; }
        .badge-cargo { background: #e5e7eb; color: #374151; }
        .empty { padding: 16px; border: 1px dashed #d1d5db; border-radius: 8px; color: #6b7280; text-align: center; }
        .note { color: #4b5563; font-size: 11px; }
        .footer { margin-top: 22px; border-top: 1px solid #e5e7eb; padding-top: 10px; color: #6b7280; font-size: 10px; text-align: center; }
    </style>
</head>
<body>
    <div class="document">
        <table class="topbar" width="100%">
            <tr>
                <td class="brand">
                    <span class="brand-mark">AC</span>
                    <span>
                        <p class="brand-name">Abogados en Colombia S.A.S.</p>
                        <p class="muted" style="margin: 2px 0 0;">Asesoria legal y financiera</p>
                    </span>
                </td>
                <td class="doc-meta">
                    <h1>Estado de cuenta</h1>
                    <p class="muted" style="margin: 5px 0 0;">Contrato de honorarios #{{ $contrato->id }}</p>
                    <p style="margin: 8px 0 0;"><span class="label">Emision</span><br>{{ Carbon::now()->format('d/m/Y') }}</p>
                </td>
            </tr>
        </table>

        <div class="section">
            <table class="info-table">
                <tr>
                    <td><div class="label">Cliente</div><div class="value">{{ $clienteNombre }}</div></td>
                    <td><div class="label">Modalidad</div><div class="value">{{ $modalidad ?: 'N/A' }}</div></td>
                    <td><div class="label">Estado</div><div class="value">{{ $estado ?: 'N/A' }}</div></td>
                    <td><div class="label">Inicio</div><div class="value">{{ $date($contrato->inicio ?? null) }}</div></td>
                </tr>
            </table>
        </div>

        <div class="section">
            <table class="summary-table">
                <tr>
                    <td><div class="label">Base contrato</div><div class="amount">{{ $money($contrato->monto_total) }}</div></td>
                    <td><div class="label">Cargos</div><div class="amount">{{ $money($totalCargosValor) }}</div></td>
                    <td><div class="label">Mora</div><div class="amount danger">{{ $money($totalMora ?? 0) }}</div></td>
                    <td class="summary-total"><div class="label">Total cobrado</div><div class="amount">{{ $money($granTotal) }}</div></td>
                    <td class="summary-paid"><div class="label">Pagado</div><div class="amount">{{ $money($totalPagado) }}</div></td>
                    <td class="summary-balance"><div class="label">Saldo</div><div class="amount">{{ $money($saldoPendiente) }}</div></td>
                </tr>
            </table>
        </div>

        <div class="section panel">
            <div class="panel-head"><h2>Movimientos pagados</h2></div>
            <div class="panel-body" style="padding: 0;">
                @if($transacciones->isEmpty())
                    <div class="empty" style="margin: 12px;">No hay pagos registrados hasta la fecha.</div>
                @else
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 16%;">Fecha</th>
                                <th>Concepto</th>
                                <th style="width: 16%;">Tipo</th>
                                <th class="text-right" style="width: 20%;">Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($transacciones as $item)
                                <tr>
                                    <td>{{ $item['fecha']->format('d/m/Y') }}</td>
                                    <td>
                                        <span class="strong">{{ $item['concepto'] }}</span>
                                        @if(!empty($item['detalle']))
                                            <br><span class="muted">{{ $item['detalle'] }}</span>
                                        @endif
                                    </td>
                                    <td><span class="badge badge-{{ strtolower($item['tipo']) }}">{{ $item['tipo'] }}</span></td>
                                    <td class="text-right strong">{{ $money($item['valor']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        @if(!$cuotasPendientes->isEmpty())
            <div class="section panel">
                <div class="panel-head"><h2>Cuotas pendientes</h2></div>
                <div class="panel-body" style="padding: 0;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th class="text-center" style="width: 12%;">Cuota</th>
                                <th style="width: 18%;">Vencimiento</th>
                                <th class="text-right">Valor</th>
                                <th class="text-right">Mora</th>
                                <th class="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($cuotasPendientes as $cuota)
                                @php
                                    $moraCuota = (float) ($cuota->intereses_mora_acumulados ?? 0);
                                    $totalCuota = (float) ($cuota->valor ?? 0) + $moraCuota;
                                @endphp
                                <tr>
                                    <td class="text-center strong">#{{ $cuota->numero }}</td>
                                    <td>{{ $date($cuota->fecha_vencimiento) }}</td>
                                    <td class="text-right">{{ $money($cuota->valor) }}</td>
                                    <td class="text-right danger">{{ $money($moraCuota) }}</td>
                                    <td class="text-right strong">{{ $money($totalCuota) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if(!$cargosPendientes->isEmpty())
            <div class="section panel">
                <div class="panel-head"><h2>Cargos pendientes</h2></div>
                <div class="panel-body" style="padding: 0;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 18%;">Fecha</th>
                                <th>Descripcion</th>
                                <th style="width: 16%;">Tipo</th>
                                <th class="text-right">Valor</th>
                                <th class="text-right">Mora</th>
                                <th class="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($cargosPendientes as $cargo)
                                @php
                                    $moraCargo = (float) ($cargo->intereses_mora_acumulados ?? 0);
                                    $totalCargo = (float) ($cargo->monto ?? 0) + ($cargo->tipo === 'INTERES_MORA' ? 0 : $moraCargo);
                                @endphp
                                <tr>
                                    <td>{{ $date($cargo->fecha_aplicado ?? null) }}</td>
                                    <td class="strong">{{ $cargo->descripcion ?? 'Cargo pendiente' }}</td>
                                    <td>{{ str_replace('_', ' ', $cargo->tipo ?? 'CARGO') }}</td>
                                    <td class="text-right">{{ $money($cargo->monto) }}</td>
                                    <td class="text-right danger">{{ $money($cargo->tipo === 'INTERES_MORA' ? 0 : $moraCargo) }}</td>
                                    <td class="text-right strong">{{ $money($totalCargo) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="section panel">
            <div class="panel-head"><h2>Observaciones</h2></div>
            <div class="panel-body">
                <p class="note" style="margin: 0;">Este documento resume el estado financiero del contrato a la fecha de emision. Los pagos recientes pueden requerir validacion administrativa antes de reflejarse definitivamente.</p>
            </div>
        </div>

        <div class="footer">
            Abogados en Colombia S.A.S. | Calle 44A #68A - 106, Medellin | 315 281 9233 | abogadosencolombiasas@gmail.com
        </div>
    </div>
</body>
</html>
