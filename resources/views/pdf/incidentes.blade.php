<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Lista de Incidentes</title>
    <style>
        body { font-family: sans-serif; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #dddddd; text-align: left; padding: 8px; font-size: 12px; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h2>Reporte de Incidentes Jurídicos</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Asunto</th>
                <th>Estado</th>
                <th>Responsable</th>
                <th>Fecha de Registro</th>
            </tr>
        </thead>
        <tbody>
            @forelse($incidentes as $incidente)
                <tr>
                    <td>{{ $incidente->id }}</td>
                    <td>{{ $incidente->asunto }}</td>
                    <td>{{ Str::ucfirst(str_replace('_', ' ', $incidente->estado)) }}</td>
                    <td>{{ $incidente->responsable->name ?? 'N/A' }}</td>
                    <td>{{ $incidente->fecha_registro }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">No hay datos para mostrar.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>