<x-mail::message>
# Nuevo Mensaje Recibido desde el Portal de Cliente

Has recibido un nuevo mensaje de uno de tus clientes.

**Nombre del Cliente:**  
{{ $nombreCliente }}

**Correo del Cliente:**  
[{{ $emailCliente }}](mailto:{{ $emailCliente }})

**Asunto:**  
{{ $asuntoCliente }}

**Mensaje:**

<x-mail::panel>
{{ $mensajeCliente }}
</x-mail::panel>

Puedes responder directamente a este correo para contactar al cliente.

Gracias,  
{{ config('app.name') }}
</x-mail::message>