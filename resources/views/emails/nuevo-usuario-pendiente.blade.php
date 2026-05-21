<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Nueva cuenta pendiente</title>
</head>
<body style="margin:0;background:#f3f4f6;font-family:Arial,sans-serif;color:#111827;">
    <div style="max-width:640px;margin:0 auto;padding:32px 16px;">
        <div style="background:#ffffff;border:1px solid #e5e7eb;border-radius:14px;overflow:hidden;">
            <div style="background:#111827;color:#ffffff;padding:22px 26px;">
                <h1 style="margin:0;font-size:20px;">Nueva cuenta pendiente de aprobación</h1>
                <p style="margin:8px 0 0;color:#d1d5db;font-size:14px;">Un usuario solicitó acceso al sistema.</p>
            </div>

            <div style="padding:26px;">
                <table style="width:100%;border-collapse:collapse;font-size:14px;">
                    <tr>
                        <td style="padding:10px 0;color:#6b7280;width:150px;">Nombre</td>
                        <td style="padding:10px 0;font-weight:700;">{{ $pendingUser->name }}</td>
                    </tr>
                    <tr>
                        <td style="padding:10px 0;color:#6b7280;">Correo</td>
                        <td style="padding:10px 0;font-weight:700;">{{ $pendingUser->email }}</td>
                    </tr>
                    <tr>
                        <td style="padding:10px 0;color:#6b7280;">Rol inicial</td>
                        <td style="padding:10px 0;font-weight:700;">{{ $pendingUser->tipo_usuario }}</td>
                    </tr>
                    <tr>
                        <td style="padding:10px 0;color:#6b7280;">Estado</td>
                        <td style="padding:10px 0;font-weight:700;color:#b45309;">Pendiente / inactivo</td>
                    </tr>
                </table>

                <p style="margin:22px 0;color:#374151;line-height:1.5;">
                    Para permitir el ingreso, entre al perfil del usuario, confirme el rol correcto,
                    asigne cooperativas/especialidades si aplica y active la cuenta.
                </p>

                <a href="{{ $approvalUrl }}" style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;font-weight:700;padding:12px 18px;border-radius:10px;">
                    Revisar y aprobar usuario
                </a>
            </div>
        </div>
    </div>
</body>
</html>
