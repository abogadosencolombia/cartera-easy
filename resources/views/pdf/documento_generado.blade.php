<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documento Generado</title>
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 12px; line-height: 1.6; color: #333; }
        .container { width: 100%; margin: 0 auto; }
        h1 { text-align: center; color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px; margin-bottom: 30px; }
        .section { margin-bottom: 25px; }
        .section h2 { font-size: 14px; color: #3498db; border-bottom: 1px solid #ecf0f1; padding-bottom: 5px; margin-bottom: 10px; }
        .info-grid { width: 100%; border-collapse: collapse; }
        .info-grid td { padding: 8px 0; vertical-align: top; }
        .info-grid .label { font-weight: bold; width: 150px; color: #555; }
        .footer { text-align: center; font-size: 10px; color: #7f8c8d; position: fixed; bottom: 0; width: 100%; }
        .main-content { text-align: justify; }
        .signature { margin-top: 60px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>NOTIFICACIÓN DE COBRO PREJURÍDICO</h1>

        <div class="section">
            <p><strong>Fecha de Emisión:</strong> {{ $datos['fecha_larga'] }}</p>
        </div>

        <div class="section">
            <h2>PARA:</h2>
            <table class="info-grid">
                <tr>
                    <td class="label">Deudor(a):</td>
                    <td>{{ $datos['deudor_nombre_completo'] }}</td>
                </tr>
                <tr>
                    <td class="label">Identificación:</td>
                    <td>{{ $datos['deudor_tipo_documento'] }} {{ $datos['deudor_numero_documento'] }}</td>
                </tr>
                 <tr>
                    <td class="label">Dirección:</td>
                    <td>{{ $datos['deudor_direccion'] }}, {{ $datos['deudor_ciudad'] }}</td>
                </tr>
            </table>
        </div>

        <div class="section">
            <h2>DE:</h2>
            <table class="info-grid">
                <tr>
                    <td class="label">Acreedor:</td>
                    <td>{{ $datos['cooperativa_nombre'] }}</td>
                </tr>
                <tr>
                    <td class="label">NIT:</td>
                    <td>{{ $datos['cooperativa_nit'] }}</td>
                </tr>
            </table>
        </div>

        <div class="section">
            <p><strong>REFERENCIA:</strong> Requerimiento de pago para la obligación con un saldo actual de <strong>$ {{ $datos['caso_monto_total'] }}</strong></p>
        </div>
        
        <hr>

        <div class="section main-content">
            <p>Respetado(a) señor(a) <strong>{{ $datos['deudor_nombre_completo'] }}</strong>,</p>
            <p>
                Por medio de la presente comunicación, y actuando en nombre de nuestro cliente, <strong>{{ $datos['cooperativa_nombre'] }}</strong>,
                le notificamos formalmente que la obligación crediticia asociada a su nombre presenta un saldo pendiente de pago.
            </p>
            <p>
                Nuestro sistema registra que el proceso se encuentra en la etapa de <strong>{{ $datos['caso_tipo_proceso'] }}</strong>. Le instamos a
                contactarnos de manera urgente para definir un acuerdo de pago y evitar el inicio de acciones judiciales.
            </p>
            @if(!empty($datos['observaciones_generacion']))
                <h2>Observaciones Adicionales:</h2>
                <p><em>{{ $datos['observaciones_generacion'] }}</em></p>
            @endif
        </div>

        <div class="section signature">
            <p>Atentamente,</p>
            <br><br><br>
            <p>
                <strong>{{ $datos['usuario_nombre'] }}</strong><br>
                Departamento de Cartera<br>
                {{ $datos['cooperativa_nombre'] }}
            </p>
        </div>

        <div class="footer">
            Este documento fue generado automáticamente por el sistema el día {{ $datos['fecha_corta'] }}.
        </div>
    </div>
</body>
</html>