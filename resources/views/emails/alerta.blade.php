<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Notificación</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f4f5; margin: 0; padding: 0; }
        .email-container { max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .header { background-color: #111827; padding: 25px; text-align: center; }
        .header h1 { color: #ffffff; margin: 0; font-size: 18px; font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase; }
        .content { padding: 30px 25px; color: #374151; line-height: 1.6; }
        .greeting { font-size: 16px; margin-bottom: 20px; color: #1f2937; }
        
        .alert-card { 
            background-color: #f8fafc; 
            border-left: 4px solid #3b82f6; 
            padding: 20px; 
            border-radius: 6px; 
            margin: 20px 0; 
        }
        .alert-card.urgent { border-left-color: #ef4444; background-color: #fef2f2; }
        
        .alert-title { display: block; font-weight: 700; font-size: 15px; margin-bottom: 8px; color: #111827; }
        .alert-message { display: block; color: #4b5563; }
        
        .details { font-size: 13px; color: #6b7280; background: #f3f4f6; padding: 10px; border-radius: 6px; margin-bottom: 25px; }
        
        .btn-container { text-align: center; margin-top: 30px; }
        .btn { 
            background-color: #2563eb; 
            color: #ffffff !important; 
            text-decoration: none; 
            padding: 12px 25px; 
            border-radius: 8px; 
            font-weight: 600; 
            display: inline-block;
            transition: background-color 0.3s;
        }
        .btn:hover { background-color: #1d4ed8; }
        
        .footer { background-color: #f9fafb; padding: 20px; text-align: center; font-size: 12px; color: #9ca3af; border-top: 1px solid #e5e7eb; }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1>Sistema de Gestión Jurídica</h1>
        </div>
        
        <div class="content">
            <p class="greeting">Hola, <strong>{{ $nombreUsuario }}</strong> 👋</p>
            
            <p>Tienes una nueva actualización en el sistema que requiere tu atención:</p>

            <div class="alert-card {{ str_contains(strtolower($titulo), 'vencida') || str_contains(strtolower($titulo), 'mora') ? 'urgent' : '' }}">
                <span class="alert-title">{{ $titulo }}</span>
                <span class="alert-message">{{ $mensaje }}</span>
            </div>

            @if($detalles)
                <div class="details">
                    <strong>Referencia:</strong> {{ $detalles }}
                </div>
            @endif

            <div class="btn-container">
                <a href="{{ $link }}" class="btn">Revisar Ahora</a>
            </div>
        </div>

        <div class="footer">
            <p>Este mensaje fue generado automáticamente.<br>
            © {{ date('Y') }} Abogados en Colombia SAS. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>