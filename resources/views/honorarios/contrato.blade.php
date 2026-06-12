@php
    use Illuminate\Support\Carbon;

    $clienteNombre = $cliente->nombre_completo ?? 'ID ' . ($contrato->cliente_id ?? 'N/A');
    $money = fn ($value) => '$ ' . number_format((float) ($value ?? 0), 0, ',', '.');
    $date = fn ($value) => $value ? Carbon::parse($value)->format('d/m/Y') : 'N/A';
    $modalidad = str_replace('_', ' ', (string) ($contrato->modalidad ?? ''));
    $estado = str_replace('_', ' ', (string) ($contrato->estado ?? ''));
    $anticipo = (float) ($contrato->anticipo ?? 0);
    $neto = max(0, (float) ($contrato->monto_total ?? 0) - $anticipo);
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Contrato de honorarios #{{ $contrato->id }}</title>
    <style>
        @page { margin: 28px 32px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #111827; font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; line-height: 1.45; }
        .topbar { border-bottom: 3px solid #111827; padding-bottom: 16px; margin-bottom: 18px; }
        .brand { width: 55%; vertical-align: top; }
        .doc-meta { width: 45%; text-align: right; vertical-align: top; }
        .brand-mark { display: inline-block; width: 34px; height: 34px; border-radius: 8px; background: #111827; color: #ffffff; text-align: center; line-height: 34px; font-weight: 800; font-size: 15px; margin-right: 10px; }
        .brand-name { font-size: 18px; font-weight: 800; margin: 0; }
        h1 { margin: 0; font-size: 24px; line-height: 1.1; }
        h2 { margin: 0 0 9px; font-size: 14px; text-transform: uppercase; letter-spacing: .05em; }
        .muted { color: #6b7280; }
        .label { color: #6b7280; font-size: 9px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
        .section { margin-top: 18px; page-break-inside: avoid; }
        .info-table, .data-table, .summary-table { width: 100%; border-collapse: collapse; }
        .info-table td { width: 25%; padding: 10px 12px; border: 1px solid #e5e7eb; vertical-align: top; }
        .value { margin-top: 4px; font-size: 12px; font-weight: 800; color: #111827; }
        .summary-table td { width: 25%; padding: 12px; border: 1px solid #d1d5db; vertical-align: top; }
        .summary-main { background: #111827; color: #ffffff; }
        .summary-main .label, .summary-main .value { color: #ffffff; }
        .panel { border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; }
        .panel-head { background: #f9fafb; border-bottom: 1px solid #e5e7eb; padding: 10px 12px; }
        .panel-body { padding: 12px; }
        .data-table th { background: #f3f4f6; color: #374151; font-size: 9px; letter-spacing: .05em; text-transform: uppercase; padding: 8px; border-bottom: 1px solid #d1d5db; text-align: left; }
        .data-table td { padding: 9px 8px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .data-table tr:last-child td { border-bottom: 0; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .strong { font-weight: 800; }
        .note { color: #4b5563; font-size: 11px; }
        .empty { padding: 16px; border: 1px dashed #d1d5db; border-radius: 8px; color: #6b7280; text-align: center; }
        .footer { margin-top: 22px; border-top: 1px solid #e5e7eb; padding-top: 10px; color: #6b7280; font-size: 10px; text-align: center; }
    </style>
</head>
<body>
    <table class="topbar" width="100%">
        <tr>
            <td class="brand">
                <span class="brand-mark">AC</span>
                <span>
                    <p class="brand-name">Abogados en Colombia S.A.S.</p>
                    <p class="muted" style="margin: 2px 0 0;">Contrato de honorarios</p>
                </span>
            </td>
            <td class="doc-meta">
                <h1>Contrato #{{ $contrato->id }}</h1>
                <p class="muted" style="margin: 5px 0 0;">Generado el {{ Carbon::now()->format('d/m/Y') }}</p>
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
                <td class="summary-main"><div class="label">Monto total</div><div class="value">{{ $money($contrato->monto_total) }}</div></td>
                <td><div class="label">Anticipo</div><div class="value">{{ $money($anticipo) }}</div></td>
                <td><div class="label">Neto programado</div><div class="value">{{ $money($neto) }}</div></td>
                <td><div class="label">Porcentaje litis</div><div class="value">{{ $contrato->porcentaje_litis ? number_format((float) $contrato->porcentaje_litis, 2, ',', '.') . '%' : 'No aplica' }}</div></td>
            </tr>
        </table>
    </div>

    <div class="section panel">
        <div class="panel-head"><h2>Condiciones del contrato</h2></div>
        <div class="panel-body">
            <table class="info-table">
                <tr>
                    <td><div class="label">Frecuencia</div><div class="value">{{ str_replace('_', ' ', $contrato->frecuencia_pago ?? 'N/A') }}</div></td>
                    <td><div class="label">Cuotas</div><div class="value">{{ $cuotas->count() }}</div></td>
                    <td><div class="label">Base litis</div><div class="value">{{ $contrato->monto_base_litis ? $money($contrato->monto_base_litis) : 'Pendiente' }}</div></td>
                    <td><div class="label">Contrato origen</div><div class="value">{{ $contrato->contrato_origen_id ? '#' . $contrato->contrato_origen_id : 'No aplica' }}</div></td>
                </tr>
            </table>
            @if(!empty($contrato->nota))
                <p class="note" style="margin: 12px 0 0;"><span class="strong">Nota:</span> {{ $contrato->nota }}</p>
            @endif
        </div>
    </div>

    <div class="section panel">
        <div class="panel-head"><h2>Plan de pagos</h2></div>
        <div class="panel-body" style="padding: 0;">
            @if($cuotas->isEmpty())
                <div class="empty" style="margin: 12px;">Este contrato no tiene cuotas programadas.</div>
            @else
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 12%;">Cuota</th>
                            <th style="width: 22%;">Vencimiento</th>
                            <th class="text-right">Valor</th>
                            <th class="text-right">Mora</th>
                            <th style="width: 18%;">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($cuotas as $cuota)
                            <tr>
                                <td class="text-center strong">#{{ $cuota->numero }}</td>
                                <td>{{ $date($cuota->fecha_vencimiento) }}</td>
                                <td class="text-right strong">{{ $money($cuota->valor) }}</td>
                                <td class="text-right">{{ $money($cuota->intereses_mora_acumulados ?? 0) }}</td>
                                <td>{{ str_replace('_', ' ', $cuota->estado ?? 'PENDIENTE') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    <div class="section panel">
        <div class="panel-head"><h2>Observaciones</h2></div>
        <div class="panel-body">
            <p class="note" style="margin: 0;">Documento operativo generado desde el modulo de honorarios. La gestion de pagos, cargos, soportes y liquidacion se realiza desde la vista del contrato en la plataforma.</p>
        </div>
    </div>

    <div class="footer">
        Abogados en Colombia S.A.S. | Calle 44A #68A - 106, Medellin | 315 281 9233 | abogadosencolombiasas@gmail.com
    </div>
</body>
</html>
