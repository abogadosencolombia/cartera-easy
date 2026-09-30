# Abogado Jeison: recepción administrativa

Estado al 30 de septiembre de 2026: recepción activa en producción; autonomía integral pendiente.

## Alcance publicado

- WhatsApp empresarial de Abogados, instancia `abogados`, propietario verificado. Webhook autenticado y rechazo de otra instancia.
- Cola local cifrada, deduplicación por mensaje y destinatario, distinción entre aceptación, entrega, lectura e incertidumbre. No reenviar resultados inciertos.
- Saludos, identificación de intención, captura de datos administrativos, solicitudes de atención y transcripción de audios con el proveedor propio.
- Intervención del personal bloquea inmediatamente la respuesta pendiente. Solo Sandra verificada puede devolver expresamente el chat. Un nombre, una cita o un reenvío no otorgan autoridad.
- Preguntas sobre capacidades pueden continuar una conversación dirigida a Jeison sin repetir su nombre. Esta excepción no autoriza nuevas operaciones ni levanta la toma humana.
- Consulta del programa por radicado exacto en lectura. Coincidencia del programa no prueba identidad, vigencia judicial ni vencimiento.
- Avisos operativos mínimos a Sandra y al grupo verificado; no se reenvía el histórico. Los avisos enlazan la solicitud privada sin códigos dentro del texto explicativo.
- Respuestas claras, cercanas y respetuosas. No se cuentan pasos internos, destinatarios del equipo ni detalles técnicos a clientes. No se inventan confirmaciones, pagos o citas.

## Google

`GoogleSources` solicita `openid`, `email`, `gmail.readonly` y `drive.readonly`. La cuenta está fijada a `abogadosencolombiasas@gmail.com`. Los perfiles de Gmail y Drive se comprueban antes de persistir el token.

Inicio de OAuth desde sesión administrativa, estado de un uso, PKCE y callback exacto. GET solo inicia el consentimiento; no concede permisos por sí mismo. El callback conserva comprobaciones de sesión, estado, antigüedad e identidad.

Credenciales y tokens cifrados con Laravel en el directorio privado; nunca se incluyen en Git, registros ordinarios o respuestas. Las consultas no envían automáticamente los documentos al modelo ni a clientes.

Al corte, APIs, cliente y código preparados; consentimiento pendiente ante el aviso de app en pruebas no verificada. No declarar Gmail o Drive conectados por haber configurado APIs. Las autorizaciones en modo de pruebas tienen duración limitada. Resolver publicación/verificación antes de prometer acceso permanente.

## Evidencia y límites

- Dos mensajes reales de Sandra: devolución y prueba; dos respuestas con estado READ.
- Audio real transcrito; respuesta externa suprimida cuando intervino personal.
- Consulta real del programa por radicado: una coincidencia exacta.
- 44 pruebas del bot y 11 de OAuth aprobadas en almacenamiento aislado. No prueban todas las operaciones reales.
- Grupo: avisos ACCEPTED y visibles. No equivale a lectura de todos sus miembros.
- Pendientes: consentimiento y consultas reales de Google, contenido y cobertura por documento, Monolegal y Rama Judicial de forma permanente, deduplicación entre fuentes, operaciones administrativas de escritura concretas y verificación de extremo a extremo.
- Conclusiones y actos jurídicos, acuerdos, cobros, pagos y comunicaciones jurídicas mantienen revisión humana. No hay escritura autónoma de expedientes.

## Despliegue

`scripts/install-abogados-bot.php` instala solo rutas y archivos permitidos con validación PHP y respaldo privado. Preserva el estado de activación. El servidor contiene cambios ajenos: no restablecer `/code`, no sustituir todo el checkout ni lanzar despliegue masivo de main.

Ejecución: scheduler Laravel ya existente cada minuto. No duplicar cron. La automatización de Codex supervisa; no reemplaza al worker del servidor.

Pruebas: `php tests/abogados-bot-acceptance.php` y `php tests/abogados-google-acceptance.php`.
