<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Abogado Jeison · Atención administrativa</title>
<style>body{font:16px system-ui;background:#f4f6f8;color:#182735;margin:0;padding:32px;max-width:1200px}h1{font-size:28px}article,section{background:white;border:1px solid #dbe3e8;border-radius:12px;padding:20px;margin:16px 0}small{color:#536570}.pill{display:inline-block;background:#e6f2ef;padding:5px 12px;border-radius:20px}table{border-collapse:collapse;width:100%;font-size:14px}td,th{text-align:left;padding:12px;border-bottom:1px solid #dbe3e8;vertical-align:top}p{white-space:pre-wrap;overflow-wrap:anywhere}a{color:#125c85}</style>
<h1>Abogado Jeison</h1><span class="pill">{{ $health['enabled'] ? 'Recepción administrativa activa' : 'En preparación' }}</span>
<p>Las consultas jurídicas, pagos, acuerdos y confirmaciones de citas requieren verificación humana. Este panel contiene información privada de Abogados.</p>
<section><h2>Fuentes de consulta</h2><p>Programa: consulta interna por radicado, en lectura. Monolegal: revisión directa de la sesión y contraste documental pendientes de integración permanente.</p>
<p>Google: {{ $google['connected'] ? 'Cuenta conectada en lectura' : 'Pendiente de autorización' }} · {{ $google['account'] }}</p>
@if($google['connected'])
<p>Consultas desde el chat verificado de Sandra: búsqueda de correos por asunto, radicado o fecha; búsqueda de archivos por nombre o radicado y lectura de un resultado seleccionado. Ejemplos: «Jeison, muéstrame los correos de hoy», «Jeison, busca en Drive “CLIENTES”», «Jeison, lee el primero».</p>
<p>Se muestran hasta cinco resultados por consulta y fragmentos con su fuente. Un listado no es lectura completa. Google Docs, texto, Word y PDF con texto admiten extracción limitada; los escaneados necesitan revisión visual u OCR. No se ejecutan instrucciones dentro de correos o archivos. La atención humana mantiene prioridad.</p>
@endif
@if(session('google_status'))<p role="status">{{ session('google_status') }}</p>@endif
<p>La conexión permite consultar correo y archivos para el trabajo interno de Abogados. Los permisos no permiten enviar correos, borrar, marcar, editar ni compartir archivos. Los tokens se guardan cifrados en el servidor. Las consultas y sus fuentes se registran cifradas; el contenido no se envía al modelo de chat, a clientes ni al grupo. Los documentos conservan su fuente y requieren verificación antes de una decisión jurídica.</p>
@if($google['configured'] && !$google['connected'])<p><a href="/abogados-bot/google/connect">Autorizar lectura de Gmail y Drive</a></p>@endif
</section>
<section><h2>Atención pendiente · {{ $health['openTickets'] }}</h2>
@forelse($tickets as $ticket)
<article id="{{ $ticket['id'] }}"><strong>{{ $ticket['id'] }} · {{ $ticket['category'] }}</strong><br>
<small>{{ $ticket['chat'] }} · {{ date('c',$ticket['created']) }} · {{ $ticket['state'] }}</small>
<p>{{ $ticket['text'] ?: 'Archivo o mensaje sin texto. Consultar el mensaje original en WhatsApp.' }}</p>
@if($ticket['program'])<p><strong>Contraste con el programa:</strong> {{ $ticket['program']['status'] }} · {{ $ticket['program']['checked_at'] }}<br>
@foreach($ticket['program']['cases'] as $case)Caso {{ $case['id'] }} · Radicado {{ $case['radicado'] }} · Estado registrado: {{ $case['estado_proceso'] }}<br>@endforeach
El dato del programa requiere contraste con el expediente y la providencia. No acredita identidad, vigencia ni vencimiento. Una búsqueda sin coincidencia exacta no demuestra ausencia de proceso.</p>@endif
</article>
@empty<p>No hay solicitudes registradas que requieran revisión.</p>@endforelse
</section><section><h2>Operación y entrega</h2><table><tr><th>Cola</th><th>Estado</th><th>Cantidad</th></tr>
@foreach(['events'=>'Entrada','outbox'=>'Salida'] as $key=>$label)@foreach($health[$key] as $row)<tr><td>{{ $label }}</td><td>{{ $row['state'] }}</td><td>{{ $row['total'] }}</td></tr>@endforeach @endforeach
</table><p>ACCEPTED indica aceptación del proveedor; DELIVERED y READ requieren confirmación. UNCERTAIN exige comprobar la entrega antes de reenviar.</p>
<h3>Últimos controles</h3><table>@foreach($health['health'] as $row)<tr><th>{{ $row['name'] }}</th><td>{{ $row['value'] }}</td><td>{{ date('c',$row['updated']) }}</td></tr>@endforeach</table></section></html>
