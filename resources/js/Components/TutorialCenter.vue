<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import {
    ArrowLeftIcon,
    ArrowRightIcon,
    BookOpenIcon,
    CheckCircleIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';

const page = usePage();
const isOpen = ref(false);
const isGuided = ref(false);
const currentStep = ref(0);
const currentGuideStep = ref(0);
const dialog = ref(null);
const guidedDialog = ref(null);
const saving = ref(false);
const targetRect = ref(null);
const pendingTimers = new Set();
const progressStorageKey = 'tutorial-pilot-active-step';
const routeHistoryStorageKey = 'tutorial-pilot-step-routes';
const radicadoStepStorageKey = 'tutorial-pilot-original-radicado-step';
const pilotUserId = 13;
const isPilotUser = computed(() => {
    const user = page.props.auth?.user;
    return Number(user?.id) === pilotUserId && user?.tipo_usuario === 'admin';
});
const canOpenHelp = computed(() => Boolean(page.props.auth?.user));
const hasUrgencies = computed(() => Number(page.props.auth?.urgencias?.total ?? 0) > 0);

const schedule = (callback, delay) => {
    const timerId = window.setTimeout(() => {
        pendingTimers.delete(timerId);
        callback();
    }, delay);
    pendingTimers.add(timerId);
    return timerId;
};

const slides = [
    {
        eyebrow: 'Bienvenida',
        title: 'Tu guía dentro del sistema',
        description: 'Este centro de ayuda te acompañará por las funciones principales. Puedes cerrar el recorrido, retomarlo y repetirlo cuando lo necesites.',
        tips: ['Los tutoriales no guardan ni eliminan información.', 'Usa el botón Ayuda para volver a abrir esta guía.'],
    },
    {
        eyebrow: 'Inicio',
        title: 'Dashboard y analítica',
        description: 'El Dashboard resume el estado del trabajo. Analítica permite revisar indicadores y tendencias para tomar decisiones con información consolidada.',
        tips: ['Empieza aquí para detectar pendientes y novedades.', 'Los resultados visibles dependen de tu rol y permisos.'],
    },
    {
        eyebrow: 'Trabajo diario',
        title: 'Gestión Diaria',
        description: 'Es el espacio para registrar y organizar actividades pendientes de la jornada. Úsalo para dejar claro qué debe hacerse, cuándo y sobre qué asunto.',
        tips: ['Escribe una tarea concreta y verificable.', 'Marca la actividad como completada solamente cuando la gestión termine.'],
    },
    {
        eyebrow: 'Casos',
        title: 'Dos clases de seguimiento',
        description: 'Casos Cooperativas administra cartera y seguimiento jurídico. Casos Abogados en Colombia organiza expedientes judiciales, radicados, partes, despachos y revisiones.',
        tips: ['Elige la sección según el origen y propósito del asunto.', 'Busca y filtra antes de registrar para evitar duplicados.'],
    },
    {
        eyebrow: 'Casos Cooperativas',
        title: 'Exportación y carga asistida',
        description: 'La exportación conserva el identificador interno de cada caso. Puedes revisar el archivo y usar Carga asistida para comparar los cambios antes de aplicarlos sin duplicar expedientes.',
        tips: ['Revisa los indicadores de la previsualización antes de procesar.', 'No cambies ni elimines el identificador interno de las filas existentes.'],
    },
    {
        eyebrow: 'Directorio',
        title: 'Personas y entidades',
        description: 'El Directorio centraliza personas, cooperativas, juzgados y usuarios autorizados. La información registrada aquí se reutiliza en casos y expedientes.',
        tips: ['Busca primero por identificación o nombre.', 'Completa datos de contacto y ubicación cuando estén disponibles.'],
    },
    {
        eyebrow: 'Asistencia especializada',
        title: 'GPTs Jurídicos',
        description: 'Son asistentes externos especializados por área del derecho. Sirven para orientar análisis y borradores, pero sus respuestas deben revisarse antes de utilizarlas.',
        tips: ['Selecciona el GPT correspondiente al tema jurídico.', 'No reemplazan la revisión profesional ni la validación del expediente.'],
    },
    {
        eyebrow: 'Siguiente paso',
        title: 'Aprende cada sección a tu ritmo',
        description: 'Esta es la guía general. El recorrido detallado de formularios, búsquedas, filtros, documentos y acciones se muestra solamente en los perfiles habilitados durante su validación.',
        tips: ['Usa el botón Ayuda para repetir esta guía cuando lo necesites.', 'Las funciones visibles siempre dependen de tu rol y permisos.'],
    },
];

const guidedSteps = [
    { section: 'Recorrido general', target: 'dashboard', title: 'Dashboard', description: 'Este es el punto de partida. Aquí encuentras el resumen general, pendientes y novedades que requieren atención.' },
    { section: 'Recorrido general', target: 'analitica', title: 'Analítica', description: 'Consulta indicadores y tendencias consolidadas. Úsala para entender el comportamiento del trabajo y apoyar decisiones.' },
    { section: 'Recorrido general', target: 'gestion-diaria', title: 'Gestión Diaria', description: 'Abre el panel donde registras las actividades concretas de tu jornada. Cada tarea debe indicar claramente qué hacer y sobre qué asunto.' },
    { section: 'Recorrido general', target: 'casos', title: 'Casos', description: 'Este menú reúne Casos Cooperativas y Casos Abogados en Colombia. Cada sección tendrá su propio tutorial de búsqueda, filtros, creación y edición.' },
    { section: 'Recorrido general', target: 'directorio', title: 'Directorio', description: 'Aquí administras personas y entidades reutilizadas en todo el sistema. Siempre debes buscar antes de crear un registro nuevo.' },
    { section: 'Recorrido general', target: 'administracion', title: 'Administración', description: 'Contiene contratos y, según tus permisos, las herramientas administrativas, auditorías, tareas y reglas del sistema.' },
    { section: 'Recorrido general', target: 'gpts-juridicos', title: 'GPTs Jurídicos', description: 'Abre asistentes especializados y enlaces jurídicos externos. Sus resultados sirven como apoyo y siempre requieren revisión profesional.' },
    { section: 'Gestión Diaria', target: 'gestion-panel-resumen', title: 'Tu hoja de ruta', description: 'El panel resume gestiones pendientes, terminadas y totales. El contador rojo del menú indica cuántas actividades siguen abiertas.' },
    { section: 'Gestión Diaria', target: 'gestion-descripcion', title: 'Describe una acción concreta', description: 'Indica exactamente qué debe hacerse. Un buen ejemplo es “Radicar memorial de impulso procesal”; evita textos vagos como “Revisar caso”.' },
    { section: 'Gestión Diaria', target: 'gestion-despacho', title: 'Selecciona el despacho o entidad', description: 'Escribe al menos dos caracteres y elige un resultado. Si no aparece, verifica la escritura; el registro puede quedar con el nombre digitado cuando corresponda.' },
    { section: 'Gestión Diaria', target: 'gestion-recordatorio', title: 'Define cuándo debe atenderse', description: 'Selecciona una fecha y hora futura o usa los accesos rápidos. El semáforo cambiará conforme se acerque el vencimiento.' },
    { section: 'Gestión Diaria', target: 'gestion-vinculacion', title: 'Vincula el asunto correcto', description: 'Si la gestión pertenece a un radicado, caso de cooperativa o contrato, selecciona el tipo y búscalo. Usa “Registro libre” cuando no corresponda a ninguno.' },
    { section: 'Gestión Diaria', target: 'gestion-anexos', title: 'Adjunta soportes si hacen falta', description: 'Puedes agregar hasta tres archivos PDF, imágenes, documentos Word o Excel. Revisa el nombre del archivo antes de continuar.' },
    { section: 'Gestión Diaria', target: 'gestion-registrar', title: 'Registra la gestión', description: 'Antes de guardar, comprueba descripción, despacho y recordatorio. El tutorial nunca pulsa este botón ni crea información por ti.' },
    { section: 'Gestión Diaria', target: 'gestion-listado', title: 'Haz seguimiento y finaliza', description: 'Las actividades aparecen aquí con su semáforo. Márcalas como hechas solamente cuando la gestión haya terminado; una actividad finalizada puede eliminarse con confirmación.' },
    { section: 'Casos Cooperativas', routeName: 'casos.index', target: 'casos-index-header', title: 'Gestión de Casos', description: 'Esta sección administra la cartera de cooperativas y su seguimiento jurídico. Desde aquí puedes consultar, filtrar, exportar y registrar expedientes.' },
    { section: 'Casos Cooperativas', routeName: 'casos.index', target: 'casos-indicadores', title: 'Indicadores rápidos', description: 'Las tarjetas resumen el estado de los casos. Algunas funcionan como filtros rápidos; vuelve a pulsarlas para desactivar el filtro.' },
    { section: 'Casos Cooperativas', routeName: 'casos.index', target: 'casos-filtros', title: 'Busca antes de registrar', description: 'Busca por nombre, documento, pagaré, radicado u otros datos disponibles. Este paso ayuda a evitar registros duplicados.' },
    { section: 'Casos Cooperativas', routeName: 'casos.index', target: 'casos-filtros-toggle', title: 'Filtros avanzados', description: 'Despliega este panel para combinar cooperativa, responsable, etapa, despacho, entidad y fechas. Si no obtienes resultados, limpia filtros y prueba con menos condiciones.' },
    { section: 'Casos Cooperativas', routeName: 'casos.index', target: 'casos-listado', title: 'Listado y vista rápida', description: 'Aquí aparecen los resultados. Selecciona un expediente para revisar su resumen y utiliza sus acciones para consultar, editar o continuar el seguimiento.' },
    { section: 'Casos Cooperativas', routeName: 'casos.index', target: 'casos-registrar', title: 'Registrar un caso', description: 'Utiliza este botón únicamente después de comprobar que el caso no existe. Al continuar, el tutorial abrirá el formulario sin guardar información.' },
    { section: 'Crear Caso Cooperativa', routeName: 'casos.create', target: 'casos-partes', title: 'Partes involucradas', description: 'El formulario está dividido en bloques. Los campos con asterisco son obligatorios; los demás se completan únicamente cuando la información esté disponible.' },
    { section: 'Crear Caso Cooperativa', routeName: 'casos.create', target: 'casos-cooperativa', title: 'Cooperativa o empresa — obligatorio', description: 'Busca y selecciona la entidad propietaria del caso. Si no aparece, no inventes otra opción: primero debe registrarse o revisarse en el Directorio de Cooperativas.' },
    { section: 'Crear Caso Cooperativa', routeName: 'casos.create', target: 'casos-responsables', title: 'Responsables — obligatorio', description: 'Asigna uno o varios abogados encargados del seguimiento. Deben ser usuarios existentes y con responsabilidad real sobre el expediente.' },
    { section: 'Crear Caso Cooperativa', routeName: 'casos.create', target: 'casos-deudor', title: 'Deudor principal — obligatorio', description: 'Busca primero por nombre o documento. Si existe, selecciónalo para reutilizar su información. Usa “Registrar Nuevo” solo después de confirmar que no aparece; nombre, tipo y número de documento son obligatorios, y los datos de contacto y vinculación se completan cuando estén disponibles.' },
    { section: 'Crear Caso Cooperativa', routeName: 'casos.create', target: 'casos-credito-proceso', title: 'Información del crédito y proceso', description: 'Este bloque reúne identificadores, valores, fechas, clasificación procesal, despacho y enlaces. Completa los datos exactamente como constan en los soportes.' },
    { section: 'Crear Caso Cooperativa', routeName: 'casos.create', target: 'casos-identificadores', title: 'Pagaré y radicado — opcionales inicialmente', description: 'Registra el número de pagaré y el radicado cuando existan. No uses puntos ni guiones. El sistema permite abrir el caso sin ellos y completarlos posteriormente.' },
    { section: 'Crear Caso Cooperativa', routeName: 'casos.create', target: 'casos-valores', title: 'Valores financieros', description: 'El monto del crédito es obligatorio. Capital, intereses y costas deben copiarse de la fuente correspondiente; no completes valores estimados como si fueran definitivos.' },
    { section: 'Crear Caso Cooperativa', routeName: 'casos.create', target: 'casos-enlaces-digitales', title: 'Carpeta de Drive y expediente digital', description: 'Si la carpeta de Drive no existe, debes crearla como “DOCUMENTO - NOMBRE COMPLETO - NÚMERO DE PAGARÉ” y pegar aquí el enlace compartido. El enlace Tyba, Samai o equivalente se agrega cuando la entidad judicial lo habilite.' },
    { section: 'Crear Caso Cooperativa', routeName: 'casos.create', target: 'casos-codeudores', title: 'Codeudores', description: 'Agrega cada codeudor cuando exista. Nombre y documento son obligatorios para cada uno; contacto, direcciones y enlaces son opcionales. Si el caso no tiene codeudores, marca expresamente “No aplica”.' },
    { section: 'Crear Caso Cooperativa', routeName: 'casos.create', target: 'casos-guardar', title: 'Revisión final', description: 'Antes de aperturar el expediente, revisa cooperativa, responsables, deudor, valores y posibles duplicados. El tutorial no pulsa el botón ni guarda el formulario.' },
    { section: 'Después de registrar', routeName: 'casos.index', target: 'casos-primer-registro', title: 'Cada fila resume un expediente', description: 'Al pulsar una fila se abre una vista rápida sin abandonar el listado. Allí puedes revisar partes, estado, finanzas, documentos y datos de contacto antes de decidir qué acción realizar.' },
    { section: 'Después de registrar', routeName: 'casos.index', target: 'casos-acciones-primer-registro', title: 'Acciones disponibles', description: 'Cerrar finaliza el proceso con una nota; fijar mantiene el caso al inicio; el ojo abre el expediente; y el menú permite editar o eliminar. Eliminar retira el caso del listado activo, conserva sus datos y siempre exige confirmación.' },
    { section: 'Visualizar expediente', routeFromTarget: 'casos-ver-primer', target: 'casos-show-header', title: 'Consulta completa del expediente', description: 'Esta pantalla muestra toda la información del caso seleccionado. El recorrido usa un expediente visible como ejemplo y no modifica ninguno de sus datos.' },
    { section: 'Visualizar expediente', target: 'casos-show-summary', title: 'Resumen procesal y financiero', description: 'Revisa el estado, cooperativa, capital, deuda actual, pagos y mora. Los avisos “Por registrar” indican información pendiente que conviene completar.' },
    { section: 'Visualizar expediente', target: 'casos-show-tabs', title: 'Secciones del expediente', description: 'Resumen reúne los datos principales; Integridad muestra información faltante; Documentos administra soportes; Financiero registra pagos; Actuaciones guarda movimientos; e Historial conserva la trazabilidad.' },
    { section: 'Visualizar expediente', target: 'casos-show-actions', title: 'Acciones superiores', description: 'Puedes copiar datos, editar el caso y gestionar su contrato de honorarios. Copiar datos no altera el expediente; editar sí permite cambios y debe hacerse con soportes verificables.' },
    { section: 'Editar expediente', routeFromTarget: 'casos-show-edit-link', target: 'casos-edit-header', title: 'Edición del caso', description: 'La edición conserva la información existente para corregirla o completarla. Un caso cerrado o bloqueado puede limitar cambios según los permisos.' },
    { section: 'Editar expediente', target: 'casos-edit-tabs', title: 'Edita por secciones', description: 'Utiliza las pestañas para actualizar información principal, proceso, codeudores, ubicaciones y notas. Cambia solamente los datos que puedas confirmar.' },
    { section: 'Editar expediente', target: 'casos-edit-save', title: 'Guardar cambios', description: '“Actualizar caso” aplica las modificaciones realizadas. Revisa los campos antes de pulsarlo; el tutorial únicamente lo señala y nunca guarda cambios automáticamente.' },
    { section: 'Abogados en Colombia', routeName: 'procesos.index', target: 'radicados-index-header', title: 'Expedientes judiciales', description: 'Esta sección organiza los procesos de Abogados en Colombia: radicado, partes, despacho, etapa, responsables y fechas de revisión.' },
    { section: 'Abogados en Colombia', routeName: 'procesos.index', target: 'radicados-indicadores', title: 'Indicadores y alertas', description: 'Las tarjetas muestran expedientes vencidos, sin radicado, cerrados o con información incompleta. Algunas pueden usarse como filtros rápidos.' },
    { section: 'Abogados en Colombia', routeName: 'procesos.index', target: 'radicados-filtros', title: 'Buscar antes de crear', description: 'Busca por radicado, asunto, nombre, documento o despacho. Confirma que el expediente no exista antes de registrar uno nuevo.' },
    { section: 'Abogados en Colombia', routeName: 'procesos.index', target: 'radicados-filtros-toggle', title: 'Filtros avanzados', description: 'Combina estado, tipo de proceso, despacho, entidad y fechas. Si no aparecen resultados, limpia filtros y reduce las condiciones.' },
    { section: 'Abogados en Colombia', routeName: 'procesos.index', target: 'radicados-listado', title: 'Listado judicial', description: 'Cada fila resume radicado, partes, despacho, etapa y próxima revisión. Pulsar una fila abre la vista rápida; las acciones permiten revisar, fijar, visualizar, editar o finalizar.' },
    { section: 'Abogados en Colombia', routeName: 'procesos.index', target: 'radicados-registrar', title: 'Nuevo radicado', description: 'Utiliza esta opción después de descartar duplicados. El formulario está dividido en tres pasos y el tutorial no guardará información.' },
    { section: 'Crear expediente judicial', routeName: 'procesos.create', target: 'radicados-stepper', title: 'Formulario de tres pasos', description: 'Identificación define el proceso; Partes Procesales registra personas y responsables; y Seguimiento programa controles, enlaces y observaciones.' },
    { section: 'Crear expediente judicial', routeName: 'procesos.create', activateTarget: 'radicados-step-button-1', target: 'radicados-identificacion', title: 'Identificación del proceso', description: 'El formulario solicita el asunto y exige seleccionar el tipo de proceso. Juzgado o entidad, radicado, fecha y etapa pueden completarse cuando estén disponibles; no inventes números ni fechas.' },
    { section: 'Crear expediente judicial', routeName: 'procesos.create', activateTarget: 'radicados-step-button-2', target: 'radicados-responsables', title: 'Responsables y representación', description: 'El abogado o gestor principal es obligatorio. El responsable de revisión es opcional. Indica correctamente si representamos al demandante o al demandado.' },
    { section: 'Crear expediente judicial', routeName: 'procesos.create', activateTarget: 'radicados-step-button-2', target: 'radicados-demandantes', title: 'Demandantes o accionantes', description: 'Busca cada persona antes de crearla. Si no existe, registra nombre, documento y empresa vinculada. Usa “Sin información” únicamente cuando la parte todavía no haya sido identificada.' },
    { section: 'Crear expediente judicial', routeName: 'procesos.create', activateTarget: 'radicados-step-button-2', target: 'radicados-demandados', title: 'Demandados o accionados', description: 'Agrega todas las partes conocidas y evita duplicarlas. Para una persona nueva, nombre, documento y empresa vinculada son los datos principales.' },
    { section: 'Crear expediente judicial', routeName: 'procesos.create', activateTarget: 'radicados-step-button-3', target: 'radicados-proxima-revision', title: 'Próxima revisión — obligatoria', description: 'Define cuándo debe revisarse nuevamente el proceso. Esta fecha alimenta las alertas y no debe confundirse con la fecha de radicación.' },
    { section: 'Crear expediente judicial', routeName: 'procesos.create', activateTarget: 'radicados-step-button-3', target: 'radicados-carpeta-drive', title: 'Carpeta de Drive', description: 'Si todavía no existe, créala como “DOCUMENTO - NOMBRE COMPLETO” y pega aquí el enlace con acceso para el equipo.' },
    { section: 'Crear expediente judicial', routeName: 'procesos.create', activateTarget: 'radicados-step-button-3', target: 'radicados-expediente-digital', title: 'Expediente digital de la entidad', description: 'Este enlace lo entrega la entidad después de radicar y solicitar acceso. Haz seguimiento y reitera la solicitud todas las veces necesarias hasta obtenerlo.' },
    { section: 'Crear expediente judicial', routeName: 'procesos.create', activateTarget: 'radicados-step-button-3', target: 'radicados-guardar', title: 'Finalizar registro', description: 'Revisa los tres pasos antes de guardar. El tutorial señala el botón, pero nunca registra el expediente automáticamente.' },
    { section: 'Después de registrar', routeName: 'procesos.index', target: 'radicados-primer-registro', title: 'Seguimiento desde la tabla', description: 'Cada fila muestra radicado, asunto, partes, despacho, etapa, próxima revisión y responsables. Pulsarla abre la vista rápida sin modificar el proceso.' },
    { section: 'Después de registrar', routeName: 'procesos.index', target: 'radicados-acciones-primer-registro', title: 'Acciones del expediente', description: 'Revisar actualiza la fecha de control; fijar lo mantiene arriba; el ojo abre el expediente; y el menú permite editar, finalizar, reactivar o eliminar según estado y permisos. Confirma siempre las acciones sensibles.' },
    { section: 'Visualizar expediente judicial', routeFromTarget: 'radicados-ver-primer', target: 'radicados-show-header', title: 'Consulta del expediente', description: 'Aquí puedes revisar el proceso completo. El tutorial abre el primer expediente visible como ejemplo y no altera su información.' },
    { section: 'Visualizar expediente judicial', target: 'radicados-show-summary', title: 'Resumen de control', description: 'Estas tarjetas muestran estado, etapa, despacho, próxima revisión y datos relevantes para priorizar el seguimiento.' },
    { section: 'Visualizar expediente judicial', target: 'radicados-show-tabs', title: 'Secciones disponibles', description: 'Consulta resumen, integridad, partes, documentos, actuaciones e historial. Registra soportes y movimientos en la sección correspondiente para conservar la trazabilidad.' },
    { section: 'Visualizar expediente judicial', target: 'radicados-show-actions', title: 'Acciones superiores', description: 'Puedes copiar información, gestionar contrato, cambiar etapa, editar o finalizar. Cambiar etapa y finalizar modifican el expediente y deben corresponder a una actuación real.' },
    { section: 'Editar expediente judicial', routeFromTarget: 'radicados-show-edit-link', target: 'radicados-edit-header', title: 'Editar el radicado', description: 'Esta pantalla permite corregir o completar partes, juzgado, etapa, responsables, revisión y enlaces. Los expedientes cerrados se muestran en modo consulta.' },
    { section: 'Editar expediente judicial', target: 'radicados-edit-form', title: 'Actualización integral', description: 'Verifica cada sección antes de cambiarla. Mantén actualizadas las partes, la fecha de próxima revisión, la carpeta de Drive y el enlace judicial cuando la entidad lo entregue.' },
    { section: 'Editar expediente judicial', target: 'radicados-edit-save', title: 'Guardar cambios', description: '“Actualizar radicado” guarda las modificaciones. El tutorial nunca pulsa este botón; revisa los datos y soportes antes de hacerlo.' },
    { section: 'Personas', routeName: 'personas.index', target: 'personas-registrar', title: 'Directorio central de personas', description: 'Aquí se concentra la información de clientes, deudores, demandados y demás contactos. Si una persona fue creada rápidamente desde Casos u otra sección, entra después a su perfil y completa toda la información verificable posible: así será más fácil encontrarla, contactarla y entender sus relaciones.' },
    { section: 'Personas', routeName: 'personas.index', target: 'personas-filtros', title: 'Buscar y filtrar', description: 'Busca por nombre, identificación, correo o teléfono. También puedes combinar estado, rol, empresa, responsable y orden. Antes de crear una persona, búscala por nombre y documento para evitar duplicados; si no aparece, limpia los filtros y vuelve a comprobar.' },
    { section: 'Personas', routeName: 'personas.index', target: 'personas-listado', title: 'Resultados del directorio', description: 'El encabezado muestra cómo se organiza cada registro: identidad, contacto, asignaciones y estado de datos. “Falta info” indica que conviene abrir el perfil y completar los datos pendientes.' },
    { section: 'Personas', routeName: 'personas.index', target: 'personas-acciones-primer-registro', title: 'Acciones de cada persona', description: 'El ojo abre la ficha completa; el lápiz permite editar; y la papelera suspende el registro sin borrarlo definitivamente. Desde el filtro Estado puedes consultar la papelera y restaurar una persona.' },
    { section: 'Registrar persona', routeName: 'personas.create', target: 'personas-identidad', title: 'Identidad — obligatoria', description: 'Nombre o razón social, tipo y número de documento, y rol son los datos principales. Escríbelos como aparecen en el soporte. Fecha de expedición, nacimiento y estado de cartera se completan cuando apliquen y estén confirmados.' },
    { section: 'Registrar persona', routeName: 'personas.create', target: 'personas-contacto', title: 'Información de contacto', description: 'Registra celular y correo principales siempre que estén disponibles; los alternativos son opcionales. Si todavía no conoces un dato, déjalo vacío y complétalo después: no uses números o correos inventados.' },
    { section: 'Registrar persona', routeName: 'personas.create', target: 'personas-direcciones', title: 'Direcciones — opcionales', description: 'Añade casa, oficina u otras ubicaciones conocidas, indicando etiqueta, ciudad y dirección completa. Puedes registrar varias y eliminar una fila equivocada antes de guardar.' },
    { section: 'Registrar persona', routeName: 'personas.create', target: 'personas-laboral', title: 'Información laboral y observaciones', description: 'Empresa, cargo y observaciones son opcionales, pero ayudan a la cobranza y al seguimiento. Anota únicamente información útil y verificable, con el contexto necesario para que otro integrante del equipo la entienda.' },
    { section: 'Registrar persona', routeName: 'personas.create', target: 'personas-asignaciones', title: 'Empresas y responsables', description: 'Vincula las cooperativas o empresas relacionadas y asigna los responsables correctos. La relación empresarial es obligatoria cuando aplica; si realmente no existe una entidad vinculada, usa la opción prevista para persona sin empresa en lugar de escoger una incorrecta.' },
    { section: 'Registrar persona', routeName: 'personas.create', target: 'personas-enlaces', title: 'Redes y enlaces — opcionales', description: 'Agrega perfiles o enlaces digitales útiles, indicando plataforma y URL completa. Este bloque puede dejarse vacío si no hay información confirmada.' },
    { section: 'Registrar persona', routeName: 'personas.create', target: 'personas-guardar', title: 'Revisar y registrar', description: 'Antes de finalizar, verifica especialmente nombre, documento, rol y asignaciones, y confirma nuevamente que no exista un duplicado. El recorrido únicamente señala el botón y nunca registra una persona.' },
    { section: 'Consultar persona', routeName: 'personas.index', target: 'personas-acciones-primer-registro', title: 'Abrir una ficha existente', description: 'Ahora veremos un registro visible como ejemplo. El recorrido no modificará ni eliminará información.' },
    { section: 'Consultar persona', routeFromTarget: 'personas-ver-primer', target: 'personas-show-summary', title: 'Resumen del perfil', description: 'Las tarjetas resumen documentos, contactos, expedientes y nivel de información. Si el perfil fue creado desde otra sección, revisa los avisos de datos pendientes y completa todo lo posible para mantener el directorio útil y completo.' },
    { section: 'Consultar persona', target: 'personas-show-profile', title: 'Datos personales y contexto', description: 'Aquí se consultan identificación, fechas, contacto, información laboral y observaciones. Los textos “no registrado” señalan datos que pueden completarse cuando se obtenga una fuente confiable.' },
    { section: 'Consultar persona', target: 'personas-show-documents', title: 'Documentos adjuntos', description: 'Puedes adjuntar soportes al perfil y, según el tipo de archivo, verlos, descargarlos o eliminarlos. Ver y descargar no alteran el registro; eliminar sí requiere cuidado porque retira el soporte.' },
    { section: 'Consultar persona', target: 'personas-show-cases', title: 'Expedientes relacionados', description: 'Estas listas conectan a la persona con sus casos de cobro y radicados judiciales. Usa “Ver” para entrar al expediente correspondiente y mantener la información en la sección adecuada.' },
    { section: 'Consultar persona', target: 'personas-show-edit-link', title: 'Completar o corregir el perfil', description: 'Editar permite completar la información faltante o corregir datos con respaldo. Es especialmente recomendable después de crear a la persona desde un formulario rápido de otra sección.' },
    { section: 'Editar persona', routeFromTarget: 'personas-show-edit-link', target: 'personas-edit-header', title: 'Edición integral del perfil', description: 'Revisa identidad, contactos, direcciones, información laboral, empresas, responsables y enlaces. Conserva los datos válidos y modifica solamente aquello que puedas confirmar.' },
    { section: 'Editar persona', target: 'personas-edit-save', title: 'Guardar cambios', description: '“Guardar cambios” actualiza el perfil. Revisa el formulario antes de pulsarlo; el recorrido nunca guarda ni altera información automáticamente.' },
    { section: 'Cooperativas y empresas', routeName: 'cooperativas.index', target: 'cooperativas-busqueda', title: 'Directorio de entidades', description: 'Aquí se administran cooperativas, empresas y entidades que luego se vinculan con Personas y Casos. Busca por razón social, NIT o correo antes de crear una nueva para evitar duplicados.' },
    { section: 'Cooperativas y empresas', routeName: 'cooperativas.index', target: 'cooperativas-listado', title: 'Entidades registradas', description: 'Cada tarjeta resume razón social, NIT, representante y contacto. “Gestionar” abre la ficha; el lápiz edita y la papelera elimina únicamente cuando tus permisos lo permiten. Revisa sus relaciones antes de eliminar.' },
    { section: 'Cooperativas y empresas', routeName: 'cooperativas.index', target: 'cooperativas-registrar', title: 'Crear una entidad', description: 'Si la entidad no aparece después de limpiar la búsqueda y verificar el NIT, regístrala aquí con información tomada de documentos oficiales. El recorrido abrirá el formulario, pero no guardará datos.' },
    { section: 'Registrar entidad', routeName: 'cooperativas.create', target: 'cooperativas-identidad', title: 'Identidad jurídica', description: 'Razón social, NIT con dígito de verificación y fecha de constitución son obligatorios. Matrícula mercantil y entidad de vigilancia se completan según el certificado o documento correspondiente; no inventes datos faltantes.' },
    { section: 'Registrar entidad', routeName: 'cooperativas.create', target: 'cooperativas-contacto', title: 'Representación y contacto', description: 'Registra representante legal y cédula, enlace administrativo, teléfono, correo de gestión y buzón de notificaciones judiciales. Confirma que el correo judicial sea el oficial, porque se usa para comunicaciones sensibles.' },
    { section: 'Registrar entidad', routeName: 'cooperativas.create', target: 'cooperativas-politicas', title: 'Políticas operativas', description: 'Indica la tasa máxima moratoria, ciudad de operación, garantía frecuente y si usa libranza o exige carta de instrucciones. Estos datos deben corresponder a las políticas reales de la entidad y sus soportes.' },
    { section: 'Registrar entidad', routeName: 'cooperativas.create', target: 'cooperativas-guardar', title: 'Revisión antes de guardar', description: 'Verifica especialmente razón social, NIT, representante, contactos y políticas. El tutorial no pulsa “Finalizar registro” ni crea información automáticamente.' },
    { section: 'Consultar entidad', routeName: 'cooperativas.index', target: 'cooperativas-listado', title: 'Abrir una entidad existente', description: 'Usaremos la primera entidad visible como ejemplo. La consulta no modifica sus datos.' },
    { section: 'Consultar entidad', routeFromTarget: 'cooperativas-ver-primera', target: 'cooperativas-show-resumen', title: 'Ficha completa de la entidad', description: 'Aquí se reúnen identificación, estado, políticas de cobro, representación y contactos. Revisa esta ficha cuando una asignación o dato empresarial parezca incorrecto.' },
    { section: 'Consultar entidad', target: 'cooperativas-show-documentos', title: 'Documentos legales y soportes', description: 'Este repositorio permite cargar, visualizar y eliminar certificados u otros soportes, con sus fechas y estado. Antes de eliminar, confirma que el archivo no sea el respaldo vigente de la información registrada.' },
    { section: 'Consultar entidad', target: 'cooperativas-show-edit-link', title: 'Editar la entidad', description: 'Utiliza “Editar entidad” para corregir o actualizar información confirmada. Los cambios pueden afectar cómo aparece la empresa en Personas y Casos.' },
    { section: 'Editar entidad', routeFromTarget: 'cooperativas-show-edit-link', target: 'cooperativas-edit-header', title: 'Actualización integral', description: 'Revisa identidad, representación, contactos y políticas. Mantén los datos válidos y actualiza únicamente los que cuenten con una fuente confiable.' },
    { section: 'Editar entidad', target: 'cooperativas-edit-save', title: 'Guardar cambios', description: 'Este botón aplica la actualización. Comprueba los datos antes de usarlo; el recorrido nunca modifica la entidad automáticamente.' },
    { section: 'Directorio de Juzgados', routeName: 'juzgados.index', target: 'juzgados-busqueda', title: 'Buscar despachos y entidades', description: 'Este directorio alimenta la selección de juzgados y entidades en los expedientes. Busca por nombre oficial, municipio o correo; prueba también una parte distintiva del nombre y limpia la búsqueda antes de concluir que no existe.' },
    { section: 'Directorio de Juzgados', routeName: 'juzgados.index', target: 'juzgados-listado', title: 'Información disponible', description: 'La tabla muestra nombre, distrito, ubicación y contacto. El lápiz permite corregir la información y la papelera elimina el despacho. Antes de eliminar, verifica que no sea el despacho correcto de expedientes existentes.' },
    { section: 'Directorio de Juzgados', routeName: 'juzgados.index', target: 'juzgados-registrar', title: 'Registrar un despacho faltante', description: 'Si no aparece después de buscarlo cuidadosamente, crea un registro con el nombre oficial de la providencia o fuente judicial. No uses abreviaturas ambiguas ni dupliques el mismo despacho con otra escritura.' },
    { section: 'Registrar despacho', routeName: 'juzgados.create', target: 'juzgados-identidad', title: 'Identificación del despacho', description: 'El nombre oficial es obligatorio. Incluye número, especialidad, categoría y ciudad cuando formen parte de la denominación. El distrito judicial se agrega cuando esté confirmado.' },
    { section: 'Registrar despacho', routeName: 'juzgados.create', target: 'juzgados-ubicacion-contacto', title: 'Ubicación y contacto', description: 'Completa municipio, departamento, correo institucional y teléfono o extensión cuando estén disponibles. Si desconoces un dato, déjalo vacío y complétalo después; no registres contactos supuestos.' },
    { section: 'Registrar despacho', routeName: 'juzgados.create', target: 'juzgados-guardar', title: 'Guardar el despacho', description: 'Revisa nombre y ubicación y realiza una última búsqueda para evitar duplicados. El recorrido señala el botón, pero nunca crea el registro automáticamente.' },
    { section: 'Importar juzgados', routeName: 'juzgados.index', target: 'juzgados-importar', title: 'Carga masiva desde Excel', description: 'Esta opción administrativa sirve para incorporar varios despachos de una fuente confiable. Para uno solo es preferible “Nuevo despacho”, porque permite revisar cada dato.' },
    { section: 'Importar juzgados', routeName: 'juzgados.import.form', target: 'juzgados-import-form', title: 'Preparar el archivo', description: 'Utiliza un archivo .xlsx o .xls con los encabezados requeridos, como nombre, municipio y correo. Revisa filas duplicadas, nombres incompletos y columnas antes de seleccionarlo o arrastrarlo.' },
    { section: 'Importar juzgados', routeName: 'juzgados.import.form', target: 'juzgados-import-submit', title: 'Iniciar importación', description: 'Esta acción sí modifica el directorio en bloque. Ejecútala únicamente después de validar el archivo; el tutorial nunca la pulsa ni carga información.' },
    { section: 'Editar despacho', routeName: 'juzgados.index', target: 'juzgados-listado', title: 'Corregir un registro existente', description: 'Para actualizar un despacho, utiliza el lápiz de la fila correspondiente. El recorrido abrirá el primero visible como ejemplo y no guardará cambios.' },
    { section: 'Editar despacho', routeFromTarget: 'juzgados-editar-primero', target: 'juzgados-edit-header', title: 'Actualizar información', description: 'Corrige nombre, distrito, ubicación o contacto únicamente con una fuente confiable. Ten presente que este despacho puede estar vinculado a varios expedientes.' },
    { section: 'Editar despacho', target: 'juzgados-edit-save', title: 'Guardar actualización', description: '“Actualizar despacho” aplica los cambios. Revisa cuidadosamente la denominación oficial antes de pulsarlo; el recorrido nunca modifica datos.' },
    { section: 'GPTs Jurídicos', activateTarget: 'gpts-juridicos', target: 'gpt-penal', title: 'Penalista Experto', description: 'Úsalo para estudiar posibles delitos, elementos de responsabilidad, rutas de denuncia o defensa y procedimiento penal. Expón hechos cronológicos, intervinientes y etapa procesal; no presentes su respuesta como concepto definitivo sin verificar tipos penales, jurisprudencia y competencia.' },
    { section: 'GPTs Jurídicos', activateTarget: 'gpts-juridicos', target: 'gpt-civil', title: 'Abogado Civil Director Jurídico', description: 'Apoya el análisis de obligaciones, contratos, responsabilidad, cobro y litigios civiles. Indica negocio jurídico, incumplimiento, fechas, cuantía, pruebas y resultado buscado para obtener una respuesta más útil.' },
    { section: 'GPTs Jurídicos', activateTarget: 'gpts-juridicos', target: 'gpt-tributario', title: 'Abogado Tributario', description: 'Sirve para revisar impuestos, requerimientos, sanciones, recursos y alternativas tributarias. Especifica entidad, impuesto, periodo, acto recibido y fechas de notificación; confirma normas, vigencia y términos antes de actuar.' },
    { section: 'GPTs Jurídicos', activateTarget: 'gpts-juridicos', target: 'gpt-familia', title: 'Familia Colombia Magistrado', description: 'Úsalo en divorcio, alimentos, custodia, unión marital, sucesiones y otros asuntos familiares. Describe vínculos, menores involucrados, medidas existentes y objetivo, protegiendo especialmente los datos sensibles de niños y adolescentes.' },
    { section: 'GPTs Jurídicos', activateTarget: 'gpts-juridicos', target: 'gpt-migracion', title: 'Migración', description: 'Ofrece orientación inicial para identificar el trámite migratorio aplicable y la información que debe reunirse. Indica nacionalidad, situación actual, fechas de ingreso o permanencia y objetivo; verifica siempre requisitos en la autoridad competente.' },
    { section: 'GPTs Jurídicos', activateTarget: 'gpts-juridicos', target: 'gpt-migratorio', title: 'Migratorio Colombiano Experto', description: 'Está enfocado en visas, regularización, nacionalidad y procedimientos migratorios colombianos. Aporta antecedentes, documentos disponibles, actuaciones previas y plazos, sin ocultar rechazos o sanciones relevantes.' },
    { section: 'GPTs Jurídicos', activateTarget: 'gpts-juridicos', target: 'gpt-disciplinario', title: 'Disciplinario Experto Senior', description: 'Apoya procesos disciplinarios, análisis de faltas, defensa, pruebas y recursos. Señala régimen aplicable, cargo o profesión, autoridad, etapa, conducta investigada y fechas de notificación.' },
    { section: 'GPTs Jurídicos', activateTarget: 'gpts-juridicos', target: 'gpt-transito', title: 'Abogado de Tránsito', description: 'Úsalo para comparendos, accidentes, licencias, inmovilización y actuaciones administrativas de tránsito. Incluye autoridad, fecha, código de infracción, hechos, notificaciones y pruebas disponibles.' },
    { section: 'GPTs Jurídicos', activateTarget: 'gpts-juridicos', target: 'gpt-comercial', title: 'Comercial Abogado Estratégico', description: 'Ayuda con sociedades, contratos, negociación, títulos y conflictos empresariales. Explica quiénes participan, obligaciones, documentos, riesgos y objetivo comercial para recibir opciones comparables.' },
    { section: 'GPTs Jurídicos', activateTarget: 'gpts-juridicos', target: 'gpt-laboral', title: 'Laboral Abogado Experto', description: 'Apoya asuntos de contratación, salario, prestaciones, terminación, acoso y seguridad social. Indica tipo y duración del vínculo, funciones, pagos, novedades, comunicaciones y fechas relevantes.' },
    { section: 'GPTs Jurídicos', activateTarget: 'gpts-juridicos', target: 'gpt-administrativo', title: 'Derecho Administrativo', description: 'Úsalo para actos administrativos, recursos, contratación estatal, responsabilidad y medios de control. Aporta entidad, acto, notificación, actuaciones agotadas y términos; verifica competencia y caducidad con especial cuidado.' },
    { section: 'GPTs Jurídicos', activateTarget: 'gpts-juridicos', target: 'gpt-infancia', title: 'Infancia y Adolescencia', description: 'Está orientado a protección de menores, restablecimiento de derechos y asuntos familiares relacionados. Expón solo la información necesaria, anonimiza cuando sea posible y prioriza el interés superior del menor.' },
    { section: 'GPTs Jurídicos', activateTarget: 'gpts-juridicos', target: 'gpts-juridicos', title: 'Cómo usar cualquier GPT jurídico', description: 'Entrega hechos ordenados, jurisdicción, etapa, objetivo y extractos relevantes; pide que identifique supuestos y fuentes. Nunca copies una respuesta directamente a un escrito: revisa datos, normas, jurisprudencia, cálculos, términos y estrategia con criterio profesional. Evita compartir contraseñas o información personal innecesaria.' },
    { section: 'Herramientas jurídicas', activateTarget: 'herramientas', target: 'herramienta-consulta-procesos', title: 'Consulta de procesos', description: 'Abre la Consulta Nacional Unificada de la Rama Judicial. Úsala como primera opción para localizar procesos por número de radicación o datos permitidos de las partes y revisar despacho, actuaciones y ubicación del expediente.' },
    { section: 'Herramientas jurídicas', activateTarget: 'herramientas', target: 'herramienta-justicia21', title: 'Procesos Rama Judicial', description: 'Da acceso a la consulta de Justicia XXI, útil como fuente histórica o complementaria cuando un proceso no aparece o no muestra todo en la consulta unificada. Compara siempre radicado, despacho y partes para confirmar que sea el expediente correcto.' },
    { section: 'Herramientas jurídicas', activateTarget: 'herramientas', target: 'herramienta-monolegal', title: 'MonoLegal', description: 'Abre la plataforma de vigilancia y expedientes administrados por MonoLegal. Úsala con las credenciales autorizadas del equipo y contrasta sus novedades con la fuente judicial antes de registrar una actuación definitiva.' },
    { section: 'Herramientas jurídicas', activateTarget: 'herramientas', target: 'herramienta-publicaciones', title: 'Publicaciones Procesales', description: 'Permite consultar estados, traslados y otras publicaciones de la Rama Judicial. Ten preparados despacho, fecha o radicado y guarda el soporte de la publicación relevante en el expediente correspondiente.' },
    { section: 'Herramientas jurídicas', activateTarget: 'herramientas', target: 'herramienta-tyba', title: 'Tyba', description: 'Consulta procesos y actuaciones gestionados en Tyba. Verifica número de radicación, partes y despacho; cuando exista acceso al expediente digital, registra su enlace en el caso correspondiente y confirma que el equipo tenga permiso.' },
    { section: 'Herramientas jurídicas', activateTarget: 'herramientas', target: 'herramientas', title: 'Registrar lo consultado', description: 'Estos portales se abren en otra pestaña y no actualizan automáticamente el programa. Después de verificar una novedad, registra la actuación, próxima revisión o gestión en el expediente correcto y adjunta el soporte cuando corresponda.' },
    { section: 'Contratos y honorarios', routeName: 'honorarios.contratos.index', target: 'contratos-indicadores', title: 'Control financiero de contratos', description: 'Los indicadores resumen valor activo, saldo por recaudar, mora y cargos pendientes. Úsalos para priorizar cobro y seguimiento; las cifras dependen de que pagos, cargos y fechas estén registrados correctamente.' },
    { section: 'Contratos y honorarios', routeName: 'honorarios.contratos.index', target: 'contratos-filtros', title: 'Buscar y filtrar contratos', description: 'Busca por número de contrato, cliente, radicado, caso o pagaré y filtra por estado. Si no aparece, limpia los filtros y verifica el cliente o expediente antes de crear otro contrato.' },
    { section: 'Contratos y honorarios', routeName: 'honorarios.contratos.index', target: 'contratos-listado', title: 'Seguimiento del recaudo', description: 'Cada registro muestra modalidad, vínculo con el expediente, total, pagos, saldo, cargos, cuotas abiertas y próximo vencimiento. Abre el contrato para registrar movimientos o consultar su trazabilidad.' },
    { section: 'Contratos y honorarios', routeName: 'honorarios.contratos.index', target: 'contratos-registrar', title: 'Nuevo contrato', description: 'Crea un contrato solo cuando exista un acuerdo real y después de comprobar que no esté registrado. También puede originarse desde un caso o proceso para conservar el vínculo correcto.' },
    { section: 'Crear contrato', routeName: 'honorarios.contratos.create', target: 'contratos-datos-base', title: 'Cliente y datos iniciales', description: 'Selecciona la persona correcta, fecha de inicio y anticipo si existe. El cliente y la fecha son obligatorios; busca primero el perfil y evita crear acuerdos sobre personas duplicadas.' },
    { section: 'Crear contrato', routeName: 'honorarios.contratos.create', target: 'contratos-acuerdo', title: 'Acuerdo económico', description: 'Escoge la modalidad pactada: cuotas, pago único, litis o modalidad mixta. Registra monto, número y frecuencia de cuotas o porcentaje de éxito exactamente como consta en el acuerdo; la nota es opcional y sirve para aclaraciones.' },
    { section: 'Crear contrato', routeName: 'honorarios.contratos.create', target: 'contratos-cronograma', title: 'Cronograma y pagos iniciales', description: 'Revisa fechas y valores de cada cuota. Puedes recalcular o ajustar el cronograma y marcar pagos iniciales únicamente cuando ya fueron recibidos, indicando valor, fecha, método y referencia o comprobante.' },
    { section: 'Crear contrato', routeName: 'honorarios.contratos.create', target: 'contratos-resumen', title: 'Resumen antes de guardar', description: 'Confirma cliente, modalidad, total, anticipo, saldo y cronograma. Si el resumen no coincide con el documento acordado, corrige el formulario antes de continuar.' },
    { section: 'Crear contrato', routeName: 'honorarios.contratos.create', target: 'contratos-guardar', title: 'Guardar contrato', description: 'Esta acción crea obligaciones y movimientos financieros. El recorrido no la pulsa; guarda solamente después de revisar el acuerdo y sus soportes.' },
    { section: 'Consultar contrato', routeName: 'honorarios.contratos.index', target: 'contratos-listado', title: 'Abrir un contrato existente', description: 'Usaremos el primer contrato visible como ejemplo, sin registrar pagos ni modificar su estado.' },
    { section: 'Consultar contrato', routeFromTarget: 'contratos-ver-primero', target: 'contratos-show-header', title: 'Estado general del contrato', description: 'El encabezado identifica contrato, cliente y estado. Comprueba siempre que estés en el acuerdo correcto antes de registrar pagos, gastos o cierres.' },
    { section: 'Consultar contrato', target: 'contratos-show-actions', title: 'Acciones financieras', description: 'Según el estado puedes agregar gastos, cerrar, saldar, reabrir, reestructurar o generar liquidación. Estas acciones cambian saldos o estados; usa valores reales y confirma el efecto antes de ejecutarlas.' },
    { section: 'Consultar contrato', target: 'contratos-show-indicadores', title: 'Saldo y mora', description: 'Aquí se separan neto de cuotas, cargos y mora, total pagado y saldo pendiente. Si una cifra parece incorrecta, revisa movimientos y fechas antes de hacer una corrección.' },
    { section: 'Consultar contrato', target: 'contratos-show-tabs', title: 'Cuotas, cargos, pagos y actuaciones', description: 'Cuotas permite registrar abonos; Cargos reúne gastos y conceptos adicionales; Pagos conserva el recaudo y comprobantes; Actuaciones documenta el seguimiento. Registra cada movimiento en la pestaña correcta.' },
    { section: 'Consultar contrato', target: 'contratos-show-documentos', title: 'Documentos del contrato', description: 'Puedes generar el PDF y estado de cuenta, subir el contrato firmado, visualizarlo, reemplazarlo o eliminarlo. Reemplazar o eliminar cambia el soporte almacenado y debe hacerse con confirmación.' },
    { section: 'Consultar contrato', target: 'contratos-show-acciones', title: 'Activar o eliminar', description: 'Activar cambia el contrato a gestión vigente. Eliminar borra contrato, cuotas, cargos, pagos, actuaciones y documentos asociados; cuando el acuerdo cambió, normalmente es más seguro reestructurarlo para conservar la historia.' },
];

const slide = computed(() => slides[currentStep.value]);
const isFirst = computed(() => currentStep.value === 0);
const isLast = computed(() => currentStep.value === slides.length - 1);
const progress = computed(() => `${((currentStep.value + 1) / slides.length) * 100}%`);
const guideStep = computed(() => guidedSteps[currentGuideStep.value]);
const isFirstGuideStep = computed(() => currentGuideStep.value === 0);
const isLastGuideStep = computed(() => currentGuideStep.value === guidedSteps.length - 1);
const guideProgress = computed(() => `${((currentGuideStep.value + 1) / guidedSteps.length) * 100}%`);
const spotlightStyle = computed(() => targetRect.value ? ({
    left: `${targetRect.value.left - 6}px`,
    top: `${targetRect.value.top - 6}px`,
    width: `${targetRect.value.width + 12}px`,
    height: `${targetRect.value.height + 12}px`,
}) : {});
const guideCardStyle = computed(() => {
    if (!targetRect.value) return {};
    const cardWidth = Math.min(380, window.innerWidth - 32);
    const left = Math.min(Math.max(16, targetRect.value.left), window.innerWidth - cardWidth - 16);
    const fitsBelow = targetRect.value.bottom + 250 < window.innerHeight;
    return {
        width: `${cardWidth}px`,
        left: `${left}px`,
        top: fitsBelow ? `${targetRect.value.bottom + 18}px` : 'auto',
        bottom: fitsBelow ? 'auto' : `${window.innerHeight - targetRect.value.top + 18}px`,
    };
});

const findVisibleTarget = (targetKey = guideStep.value.target) => {
    const selector = `[data-tutorial="${targetKey}"]`;
    return [...document.querySelectorAll(selector)].find((element) => {
        const rect = element.getBoundingClientRect();
        const style = window.getComputedStyle(element);
        return rect.width > 0 && rect.height > 0 && style.display !== 'none' && style.visibility !== 'hidden';
    });
};

const updateGuidePosition = () => {
    if (!isGuided.value) return;
    const target = findVisibleTarget();
    if (!target) {
        targetRect.value = null;
        return;
    }
    try {
        const routeHistory = JSON.parse(sessionStorage.getItem(routeHistoryStorageKey) || '{}');
        routeHistory[currentGuideStep.value] = window.location.href;
        sessionStorage.setItem(routeHistoryStorageKey, JSON.stringify(routeHistory));
    } catch {
        sessionStorage.removeItem(routeHistoryStorageKey);
    }
    target.scrollIntoView({ block: 'nearest', inline: 'nearest' });
    const rect = target.getBoundingClientRect();
    targetRect.value = {
        left: rect.left,
        top: rect.top,
        right: rect.right,
        bottom: rect.bottom,
        width: rect.width,
        height: rect.height,
    };
};

const openTutorial = (step = 0) => {
    if (!canOpenHelp.value) return;
    currentStep.value = Math.min(Math.max(step, 0), slides.length - 1);
    isOpen.value = true;
    document.body.style.overflow = 'hidden';
    nextTick(() => dialog.value?.focus());
};

const markSeen = async () => {
    const user = page.props.auth?.user;
    if (!user || user.tour_seen || saving.value) return;

    saving.value = true;
    try {
        await axios.patch(route('profile.tour.seen'));
        user.tour_seen = true;
    } catch (error) {
        console.error('No fue posible guardar el progreso del tutorial.', error);
    } finally {
        saving.value = false;
    }
};

const closeTutorial = async () => {
    isOpen.value = false;
    document.body.style.overflow = '';
    await markSeen();
};

const next = () => {
    if (isLast.value) {
        if (isPilotUser.value) {
            startGuidedTour();
        } else {
            closeTutorial();
        }
        return;
    }
    currentStep.value += 1;
};

const startGuidedTour = () => {
    const confirmed = window.confirm(
        'El recorrido guiado cambiará de página. Guarda primero cualquier formulario o texto pendiente para no perderlo. ¿Deseas continuar?',
    );
    if (!confirmed) return;

    isOpen.value = false;
    isGuided.value = true;
    currentGuideStep.value = 0;
    sessionStorage.setItem(progressStorageKey, '0');
    sessionStorage.removeItem(routeHistoryStorageKey);
    nextTick(() => {
        guidedDialog.value?.focus();
        if (!findVisibleTarget()) {
            document.querySelector('[data-tutorial-menu-toggle]')?.click();
            nextTick(updateGuidePosition);
            return;
        }
        updateGuidePosition();
    });
};

const rememberRadicadoStep = () => {
    if (!route().current('procesos.create') || sessionStorage.getItem(radicadoStepStorageKey)) return;

    const currentButton = document.querySelector('[data-tutorial^="radicados-step-button-"][aria-current="step"]');
    const currentTarget = currentButton?.dataset.tutorial;
    const currentStepId = currentTarget?.match(/(\d+)$/)?.[1];
    if (currentStepId) sessionStorage.setItem(radicadoStepStorageKey, currentStepId);
};

const restoreRadicadoStep = () => {
    const originalStep = sessionStorage.getItem(radicadoStepStorageKey);
    if (!originalStep) return;

    document.querySelector(`[data-tutorial="radicados-step-button-${originalStep}"]`)?.click();
    sessionStorage.removeItem(radicadoStepStorageKey);
};

const finishGuidedTour = async () => {
    restoreRadicadoStep();
    isGuided.value = false;
    targetRect.value = null;
    sessionStorage.removeItem(progressStorageKey);
    sessionStorage.removeItem(routeHistoryStorageKey);
    sessionStorage.removeItem(radicadoStepStorageKey);
    window.dispatchEvent(new CustomEvent('close-gestion-diaria'));
    document.body.style.overflow = '';
    await markSeen();
};

const prepareGuideStep = () => {
    sessionStorage.setItem(progressStorageKey, String(currentGuideStep.value));
    if (!guideStep.value.target.startsWith('gestion-')) {
        window.dispatchEvent(new CustomEvent('close-gestion-diaria'));
    }
    if (guideStep.value.routeFromTarget && !findVisibleTarget()) {
        const routeSource = findVisibleTarget(guideStep.value.routeFromTarget);
        const href = routeSource?.href;
        if (href) {
            restoreRadicadoStep();
            window.dispatchEvent(new CustomEvent('close-gestion-diaria'));
            isGuided.value = false;
            targetRect.value = null;
            router.visit(href);
            return;
        }
    }
    if (guideStep.value.routeName && !route().current(guideStep.value.routeName)) {
        restoreRadicadoStep();
        window.dispatchEvent(new CustomEvent('close-gestion-diaria'));
        isGuided.value = false;
        targetRect.value = null;
        router.visit(route(guideStep.value.routeName));
        return;
    }
    if (!findVisibleTarget()) {
        try {
            const routeHistory = JSON.parse(sessionStorage.getItem(routeHistoryStorageKey) || '{}');
            const previousUrl = routeHistory[currentGuideStep.value];
            if (previousUrl && previousUrl !== window.location.href) {
                restoreRadicadoStep();
                isGuided.value = false;
                targetRect.value = null;
                router.visit(previousUrl);
                return;
            }
        } catch {
            sessionStorage.removeItem(routeHistoryStorageKey);
        }
    }
    if (guideStep.value.activateTarget && !findVisibleTarget()) {
        if (guideStep.value.activateTarget.startsWith('radicados-step-button-')) {
            rememberRadicadoStep();
        }
        const competingMenu = guideStep.value.activateTarget === 'herramientas'
            ? { trigger: 'gpts-juridicos', visibleItem: 'gpt-penal' }
            : guideStep.value.activateTarget === 'gpts-juridicos'
                ? { trigger: 'herramientas', visibleItem: 'herramienta-consulta-procesos' }
                : null;
        const openCompetingMenu = competingMenu && findVisibleTarget(competingMenu.visibleItem);

        if (openCompetingMenu) {
            findVisibleTarget(competingMenu.trigger)?.click();
            schedule(() => {
                findVisibleTarget(guideStep.value.activateTarget)?.click();
                schedule(updateGuidePosition, 250);
            }, 120);
            return;
        }

        findVisibleTarget(guideStep.value.activateTarget)?.click();
        schedule(updateGuidePosition, 350);
        return;
    }
    if (guideStep.value.target.startsWith('gestion-') && guideStep.value.target !== 'gestion-diaria') {
        window.dispatchEvent(new CustomEvent('open-gestion-diaria'));
        schedule(updateGuidePosition, 350);
        return;
    }
    nextTick(updateGuidePosition);
};

const nextGuideStep = () => {
    if (isLastGuideStep.value) {
        finishGuidedTour();
        return;
    }
    currentGuideStep.value += 1;
    prepareGuideStep();
};

const previousGuideStep = () => {
    if (isFirstGuideStep.value) return;
    currentGuideStep.value -= 1;
    prepareGuideStep();
};

const previous = () => {
    if (!isFirst.value) currentStep.value -= 1;
};

const trapFocus = (event) => {
    const container = isGuided.value ? guidedDialog.value : dialog.value;
    if (!container) return;

    const focusable = [...container.querySelectorAll(
        'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
    )].filter((element) => element.getClientRects().length > 0);

    if (!focusable.length) {
        event.preventDefault();
        container.focus();
        return;
    }

    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    const activeElement = document.activeElement;

    if (event.shiftKey && (activeElement === first || !container.contains(activeElement))) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && activeElement === last) {
        event.preventDefault();
        first.focus();
    }
};

const handleKeydown = (event) => {
    if (!isOpen.value && !isGuided.value) return;
    if (event.key === 'Tab') {
        trapFocus(event);
        return;
    }
    if (event.key === 'Escape') isGuided.value ? finishGuidedTour() : closeTutorial();
    if (event.key === 'ArrowRight') isGuided.value ? nextGuideStep() : next();
    if (event.key === 'ArrowLeft') isGuided.value ? previousGuideStep() : previous();
};

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
    window.addEventListener('resize', updateGuidePosition);
    window.addEventListener('scroll', updateGuidePosition, true);
    const storedStep = Number.parseInt(sessionStorage.getItem(progressStorageKey), 10);
    const urgentAlertPending = hasUrgencies.value
        && sessionStorage.getItem('urgencia_alert_dismissed') !== 'true';

    if (isPilotUser.value && !urgentAlertPending && Number.isInteger(storedStep) && storedStep >= 0 && storedStep < guidedSteps.length) {
        currentGuideStep.value = storedStep;
        isGuided.value = true;
        document.body.style.overflow = 'hidden';
        nextTick(() => guidedDialog.value?.focus());
        schedule(prepareGuideStep, 350);
        return;
    }
    const shouldIntroduceHelp = canOpenHelp.value
        && !page.props.auth.user.tour_seen
        && !urgentAlertPending
        && (isPilotUser.value || route().current('casos.index'));

    if (shouldIntroduceHelp) {
        schedule(() => openTutorial(), 700);
    }
});

onBeforeUnmount(() => {
    restoreRadicadoStep();
    pendingTimers.forEach((timerId) => window.clearTimeout(timerId));
    pendingTimers.clear();
    window.removeEventListener('keydown', handleKeydown);
    window.removeEventListener('resize', updateGuidePosition);
    window.removeEventListener('scroll', updateGuidePosition, true);
    document.body.style.overflow = '';
});
</script>

<template>
    <button
        v-if="canOpenHelp"
        type="button"
        class="fixed bottom-5 right-20 z-[90] inline-flex items-center gap-2 rounded-full bg-indigo-600 px-4 py-3 text-sm font-black text-white shadow-xl transition hover:bg-indigo-700 focus:outline-none focus:ring-4 focus:ring-indigo-300 dark:focus:ring-indigo-900"
        aria-label="Abrir centro de ayuda"
        @click="openTutorial()"
    >
        <BookOpenIcon class="h-5 w-5" />
        <span class="hidden sm:inline">Ayuda</span>
    </button>

    <Teleport to="body">
        <div
            v-if="isOpen"
            class="fixed inset-0 z-[200] flex items-center justify-center bg-gray-950/65 p-4 backdrop-blur-sm"
            role="presentation"
            @mousedown.self="closeTutorial"
        >
            <section
                ref="dialog"
                tabindex="-1"
                role="dialog"
                aria-modal="true"
                aria-labelledby="tutorial-title"
                class="w-full max-w-2xl overflow-hidden rounded-3xl bg-white shadow-2xl outline-none dark:bg-gray-800"
            >
                <div class="h-1.5 bg-gray-100 dark:bg-gray-700">
                    <div class="h-full bg-indigo-600 transition-all duration-300" :style="{ width: progress }"></div>
                </div>

                <div class="p-6 sm:p-8">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-black uppercase tracking-[0.2em] text-indigo-600 dark:text-indigo-400">{{ slide.eyebrow }}</p>
                            <h2 id="tutorial-title" class="mt-2 text-2xl font-black tracking-tight text-gray-950 dark:text-white sm:text-3xl">{{ slide.title }}</h2>
                        </div>
                        <button type="button" class="rounded-full p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-700 dark:hover:text-gray-200" aria-label="Cerrar tutorial" @click="closeTutorial">
                            <XMarkIcon class="h-6 w-6" />
                        </button>
                    </div>

                    <p class="mt-5 text-base font-medium leading-7 text-gray-600 dark:text-gray-300">{{ slide.description }}</p>

                    <ul class="mt-6 space-y-3">
                        <li v-for="tip in slide.tips" :key="tip" class="flex items-start gap-3 rounded-xl bg-indigo-50 px-4 py-3 text-sm font-semibold text-indigo-950 dark:bg-indigo-950/40 dark:text-indigo-100">
                            <CheckCircleIcon class="mt-0.5 h-5 w-5 shrink-0 text-indigo-600 dark:text-indigo-400" />
                            <span>{{ tip }}</span>
                        </li>
                    </ul>

                    <div class="mt-8 flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-center text-xs font-bold text-gray-400 sm:text-left">Paso {{ currentStep + 1 }} de {{ slides.length }}</p>
                        <div class="flex gap-2">
                            <button type="button" class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-bold text-gray-600 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-600 dark:text-gray-300 sm:flex-none" :disabled="isFirst" @click="previous">
                                <ArrowLeftIcon class="h-4 w-4" /> Anterior
                            </button>
                            <button type="button" class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-black text-white hover:bg-indigo-700 sm:flex-none" @click="next">
                                {{ isLast ? (isPilotUser ? 'Comenzar recorrido' : 'Finalizar') : 'Siguiente' }}
                                <ArrowRightIcon class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div v-if="isGuided" class="fixed inset-0 z-[210]" role="dialog" aria-modal="true" :aria-label="guideStep.title">
            <div v-if="targetRect" class="pointer-events-none fixed rounded-xl ring-4 ring-white transition-all duration-300" :style="spotlightStyle" style="box-shadow: 0 0 0 9999px rgba(3, 7, 18, 0.78);"></div>
            <div v-else class="fixed inset-0 bg-gray-950/80"></div>

            <section
                ref="guidedDialog"
                tabindex="-1"
                class="fixed z-[211] max-w-[calc(100vw-2rem)] rounded-2xl bg-white p-5 shadow-2xl dark:bg-gray-800"
                :class="targetRect ? '' : 'left-1/2 top-1/2 w-[380px] -translate-x-1/2 -translate-y-1/2'"
                :style="guideCardStyle"
            >
                <div class="mb-4 h-1 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                    <div class="h-full bg-indigo-600 transition-all" :style="{ width: guideProgress }"></div>
                </div>
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-indigo-600 dark:text-indigo-400">{{ guideStep.section }} · {{ currentGuideStep + 1 }} de {{ guidedSteps.length }}</p>
                <h2 class="mt-2 text-xl font-black text-gray-950 dark:text-white">{{ guideStep.title }}</h2>
                <p class="mt-3 text-sm font-medium leading-6 text-gray-600 dark:text-gray-300">{{ guideStep.description }}</p>
                <p v-if="!targetRect" class="mt-3 rounded-lg bg-amber-50 p-3 text-xs font-bold text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">Este elemento no está visible con los datos, filtros o permisos actuales. Puedes continuar o salir para ajustar la vista.</p>
                <div class="mt-5 flex items-center justify-between gap-2">
                    <button type="button" class="text-xs font-bold text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white" @click="finishGuidedTour">Salir</button>
                    <div class="flex gap-2">
                        <button type="button" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-bold text-gray-600 disabled:opacity-30 dark:border-gray-600 dark:text-gray-300" :disabled="isFirstGuideStep" @click="previousGuideStep">Anterior</button>
                        <button type="button" class="rounded-lg bg-indigo-600 px-4 py-2 text-xs font-black text-white hover:bg-indigo-700" @click="nextGuideStep">{{ isLastGuideStep ? 'Terminar' : 'Siguiente' }}</button>
                    </div>
                </div>
            </section>
        </div>
    </Teleport>
</template>
