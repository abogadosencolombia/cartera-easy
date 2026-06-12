# Project Instructions

## Mandato de Ejecución de Pruebas (Protocolo de Validación)
- **Directiva Principal:** Tras completar cualquier acción solicitada por el usuario (modificación de código, análisis, creación de archivos, etc.), se DEBE proponer la ejecución de `php artisan test`.
- **Solicitud de Autorización:** Antes de cada ejecución, el agente debe preguntar explícitamente o iniciar el comando de shell para que el usuario pueda aceptar o rechazar la acción.
- **Manejo de Rechazo:** Si el usuario rechaza la ejecución (ya sea verbalmente o cancelando la herramienta `run_shell_command`), el agente debe continuar con la siguiente petición sin insistir ni marcarlo como un error crítico.
- **Persistencia:** Este protocolo es obligatorio y debe reactivarse en cada nueva interacción o tarea.
- **Riesgos:** El usuario acepta el riesgo de mutación de la base de datos durante los tests y confirma que posee respaldos.
