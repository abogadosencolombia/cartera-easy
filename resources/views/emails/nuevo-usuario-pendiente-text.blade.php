Nueva cuenta pendiente de aprobación

Nombre: {{ $pendingUser->name }}
Correo: {{ $pendingUser->email }}
Rol inicial: {{ $pendingUser->tipo_usuario }}
Estado: Pendiente / inactivo

Para permitir el ingreso, revise el perfil, asigne el rol correcto, cooperativas/especialidades si aplica, y active la cuenta:
{{ $approvalUrl }}
