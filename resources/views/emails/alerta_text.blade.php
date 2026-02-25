Hola, {{ $nombreUsuario }}

Tienes una nueva actualización en el sistema de Gestión Jurídica:

{{ strtoupper($titulo) }}
{{ $mensaje }}

@if($detalles)
Referencia: {{ $detalles }}
@endif

Para gestionar esta alerta, visita el siguiente enlace:
{{ $link }}

--------------------------------------------------
Este mensaje fue generado automáticamente.
© {{ date('Y') }} Abogados en Colombia SAS.