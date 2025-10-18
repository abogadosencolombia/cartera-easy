{{-- resources/views/honorarios/contrato.blade.php --}}
@php
  // CORREGIDO: Usamos la variable $cliente que envía el controlador.
  $clienteNombre = $cliente->nombre ?? 'ID ' . ($contrato->cliente_id);
@endphp
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Contrato</title></head>
<body>
    {{-- CORREGIDO: Se usa la variable $contrato en lugar de $c --}}
    <h2>Contrato #{{ $contrato->id }}</h2>
    <p><strong>Cliente:</strong> {{ $clienteNombre }}</p>
    <p><strong>Monto total:</strong> $ {{ number_format((int)$contrato->monto_total, 0, ',', '.') }}</p>
    <p><strong>Fecha:</strong> {{ \Illuminate\Support\Carbon::parse($contrato->inicio)->toDateString() }}</p>
</body>
</html>