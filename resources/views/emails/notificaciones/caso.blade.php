<x-mail::message>
# Nueva Notificación de Caso

Has recibido una nueva alerta.

**Caso:** {{ $notificacion->caso->nombre_caso }}
**Tipo de Alerta:** {{ $notificacion->tipo }}
**Mensaje:**
{{ $notificacion->mensaje }}

<x-mail::button :url="route('casos.show', $notificacion->caso_id)">
Ver Caso
</x-mail::button>

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>