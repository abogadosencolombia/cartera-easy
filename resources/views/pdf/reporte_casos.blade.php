<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Casos</title>
    <style>
        body {
            font-family: 'Helvetica', sans-serif;
            font-size: 10px;
            color: #333;
        }
        .report-header {
            text-align: center;
            margin-bottom: 20px;
        }
        .report-header h1 {
            margin: 0;
            font-size: 18px;
        }
        .report-header h2 {
            margin: 0;
            font-size: 14px;
            font-weight: normal;
            color: #555;
        }
        .report-table {
            width: 100%;
            border-collapse: collapse;
        }
        .report-table th, .report-table td {
            border: 1px solid #ddd;
            padding: 6px;
            text-align: left;
        }
        .report-table th {
            background-color: #f2f2f2;
            font-weight: bold;
            font-size: 11px;
        }
        .report-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .text-right {
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="report-header">
        <h1>Reporte de Cartera Judicial</h1>
        <h2>Generado el: {{ now()->format('d/m/Y H:i:s') }}</h2>
    </div>

    <table class="report-table">
        <thead>
            <tr>
                <th>ID Caso</th>
                <th>Cooperativa</th>
                <th>Deudor</th>
                <th>C.C. Deudor</th>
                <th>Gestor</th>
                <th>Tipo Proceso</th>
                <th>Estado</th>
                <th>Fecha Apertura</th>
                <th>Monto Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($casos as $caso)
                <tr>
                    <td>{{ $caso->id }}</td>
                    <td>{{ $caso->cooperativa->nombre ?? 'N/A' }}</td>
                    <td>{{ $caso->deudor->nombre_completo ?? 'N/A' }}</td>
                    <td>{{ $caso->deudor->numero_documento ?? 'N/A' }}</td>
                    <td>{{ $caso->user->name ?? 'N/A' }}</td>
                    <td>{{ $caso->tipo_proceso }}</td>
                    <td>{{ $caso->estado_proceso }}</td>
                    <td>{{ $caso->fecha_apertura->format('d/m/Y') }}</td>
                    <td class="text-right">${{ number_format($caso->monto_total, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" style="text-align: center;">No se encontraron casos con los filtros aplicados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
