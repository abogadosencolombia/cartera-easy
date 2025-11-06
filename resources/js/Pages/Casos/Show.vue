<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import SelectInput from '@/Components/SelectInput.vue';
import Checkbox from '@/Components/Checkbox.vue';
import Textarea from '@/Components/Textarea.vue';
import CumplimientoLegal from '@/Components/CumplimientoLegal.vue';
import HistorialAuditoria from '@/Components/HistorialAuditoria.vue';
import { Head, Link, useForm, usePage, useRemember, router } from '@inertiajs/vue3'; // 'router' añadido
import { ref, computed } from 'vue';

// Iconos
import {
    InformationCircleIcon,
    FolderOpenIcon,
    CreditCardIcon,
    ClipboardDocumentListIcon,
    ClockIcon,
    LockClosedIcon,
    DocumentDuplicateIcon,
    ArrowDownTrayIcon,
    BellAlertIcon,
    UserCircleIcon,
    ScaleIcon,
    TrashIcon,
    UsersIcon, // --- AÑADIDO PARA CODEUDORES ---
    LockOpenIcon,    // <-- AÑADIDO PARA DESBLOQUEAR
    
    // --- INICIO: AÑADIDOS PARA DESPLEGABLE CODEUDOR ---
    PhoneIcon,
    EnvelopeIcon,
    MapPinIcon,
    ChevronDownIcon,
    // --- FIN: AÑADIDOS PARA DESPLEGABLE CODEUDOR ---
    ChatBubbleLeftEllipsisIcon, // <-- NUEVO: Para la nota
} from '@heroicons/vue/24/outline';

const props = defineProps({
    caso: { type: Object, required: true },
    can: { type: Object, required: true },
    plantillas: { type: Array, default: () => [] },
    actuaciones: { type: Array, default: () => [] },
    // --- ¡PROP AÑADIDO! ---
    resumen_financiero: { type: Object, required: true },  
});

const page = usePage();
const activeTab = useRemember('resumen', 'casoShowTab:' + props.caso.id);

// --- INICIO: OBTENER USUARIO ACTUAL ---
const user = usePage().props.auth.user;
// --- FIN: OBTENER USUARIO ACTUAL ---


// --------- Modales y formularios ---------
const confirmingDocumentUpload = ref(false);
const docFormProgress = ref(null);

// --- INICIO: CORRECCIÓN (POLIMORFISMO) ---
// Cambiamos 'codeudor_id' por 'asociado_a'.
// Este campo guardará un string 'tipo-id' (ej. 'persona-1' o 'codeudor-5')
const docForm = useForm({
    tipo_documento: 'pagaré',
    fecha_carga: new Date().toISOString().slice(0, 10),
    archivo: null,
    asociado_a: null, // <-- CAMBIADO
    nota: '',        // <-- NUEVO
});
// --- FIN: CORRECCIÓN ---

const openUploadModal = () => {
    confirmingDocumentUpload.value = true;
};
const closeUploadModal = () => {
    confirmingDocumentUpload.value = false;
    docFormProgress.value = null;

    // --- INICIO: CORRECCIÓN (POLIMORFISMO) ---
    // Reseteamos el formulario
    docForm.reset({
        tipo_documento: 'pagaré',
        fecha_carga: new Date().toISOString().slice(0, 10),
        archivo: null,
        // Por defecto, se asocia a la persona (deudor) si existe
        asociado_a: props.caso.deudor ? `persona-${props.caso.deudor.id}` : null, // <-- CAMBIADO
        nota: '',        // <-- NUEVO
    });
    // --- FIN: CORRECCIÓN ---
};
// --- MODIFICADO: Ajustado a 128MB (131072 / 1024 / 1024) ---
const MAX_128MB = 128 * 1024 * 1024;
const submitDocument = () => {
    if (docForm.archivo && docForm.archivo.size > MAX_128MB) {
        // --- MODIFICADO: Mensaje de error corregido a 128MB ---
        docForm.setError('archivo', 'Tamaño máximo permitido: 128MB.');
        return;
    }
    docForm.post(route('casos.documentos.store', props.caso.id), {
        preserveScroll: true,
        forceFormData: true,
        onProgress: (e) => (docFormProgress.value = e?.percentage ?? null),
        onSuccess: () => closeUploadModal(),
        onFinish: () => (docFormProgress.value = null),
    });
};

const confirmingDocumentDeletion = ref(false);
const documentToDelete = ref(null);
const confirmDocumentDeletion = (documento) => {
    documentToDelete.value = documento;
    confirmingDocumentDeletion.value = true;
};
const closeDeleteModal = () => {
    confirmingDocumentDeletion.value = false;
    documentToDelete.value = null;
};
const deleteDocument = () => {
    useForm({}).delete(route('documentos-caso.destroy', documentToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => closeDeleteModal(),
    });
};

const mostrandoModalGenerar = ref(false);
const generarDocForm = useForm({
    plantilla_id: null,
    caso_id: props.caso.id,
    es_confidencial: false,
    observaciones: '',
});
const abrirModalGenerar = () => {
    generarDocForm.reset();
    generarDocForm.caso_id = props.caso.id;
    mostrandoModalGenerar.value = true;
};
const cerrarModalGenerar = () => {
    mostrandoModalGenerar.value = false;
};
const submitGenerarDocumento = () => {
    generarDocForm.post(route('documentos.generar'), {
        preserveScroll: true,
        onSuccess: () => cerrarModalGenerar(),
    });
};

// ===== SECCIÓN DE PAGO ELIMINADA =====
// Ya no se maneja desde Casos
// =====================================

const mostrandoModalAlerta = ref(false);
const alertaForm = useForm({
    mensaje: '',
    programado_para: null,
    prioridad: 'media', // default
});
const abrirModalAlerta = () => {
    alertaForm.reset();
    mostrandoModalAlerta.value = true;
};
const cerrarModalAlerta = () => {
    mostrandoModalAlerta.value = false;
};
const submitAlerta = () => {
    alertaForm.post(route('casos.notificaciones.store', props.caso.id), {
        preserveScroll: true,
        onSuccess: () => cerrarModalAlerta(),
    });
};

// --------- Utilidades de formato robustas ---------
const parseDate = (s) => {
    if (!s) return null;
    if (/^\d{4}-\d{2}-\d{2}$/.test(s)) {
        const [y, m, d] = s.split('-').map(Number);
        return new Date(y, m - 1, d);
    }
    // Corregir para manejar fechas del backend con 'T' y zona horaria
    return new Date(String(s).replace(' ', 'T'));
};
const formatDate = (s) =>
    parseDate(s)?.toLocaleDateString('es-CO', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        timeZone: 'UTC' // Usar UTC para consistencia
    }) || 'No especificada';
const formatDateTime = (s) =>
    parseDate(s)?.toLocaleString('es-CO', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        timeZone: 'UTC' // Usar UTC para consistencia
    }) || 'N/A';

// --- INICIO: Helpers de Fecha para Actuaciones ---
const fmtDateSimple = (d) => {
     if (!d) return 'N/A';
    const dateStr = String(d).replace(' ', 'T');
    const dateObj = new Date(dateStr);
    if (isNaN(dateObj.getTime())) {
        const dateOnlyMatch = String(d).match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (dateOnlyMatch) {
            const [, year, month, day] = dateOnlyMatch;
            const dateOnlyObj = new Date(Date.UTC(year, month - 1, day));
             if (!isNaN(dateOnlyObj.getTime())) {
                 return dateOnlyObj.toLocaleDateString('es-CO', { year: 'numeric', month: 'long', day: 'numeric', timeZone: 'UTC' });
             }
        }
        return 'Fecha Inválida';
    }
    return dateObj.toLocaleDateString('es-CO', { year: 'numeric', month: 'long', day: 'numeric', timeZone: 'UTC' });
};

const today = new Date().toISOString().slice(0, 10);
// --- FIN: Helpers de Fecha para Actuaciones ---

// ===== INICIO: FUNCIÓN HELPER PARA NOMBRES =====
/**
 * Función helper para limpiar los nombres (ej. 'ACCION_DE_TUTELA' -> 'Accion De Tutela')
 */
const formatLabel = (text) => {
    if (!text) return 'No especificado';
    // --- CAMBIO L4: Si no hay guiones bajos, no convertir a minúsculas (para "Por derecho a la salud")
    if (text.indexOf('_') === -1) {
        return text;
    }
    // --- FIN CAMBIO L4 ---
    return text.replace(/_/g, ' ')
               .toLowerCase()
               .replace(/\b\w/g, char => char.toUpperCase());
};
// ===== FIN: FUNCIÓN HELPER PARA NOMBRES =====

// --- INICIO: CAMBIO (Usando 'semaforo' del backend) ---
const statusColorClasses = {
    rojo: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
    amarillo: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
    verde: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
    gris: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
};
// --- FIN: CAMBIO ---

// ===== INICIO: CORRECCIÓN DE ESTADO (COPIADO DE INDEX.VUE) =====
// Colores para el estado del PROCESO
const statusProcessoClasses = {
    'prejurídico': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
    'demandado': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
    'en ejecución': 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-300',
    'sentencia': 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
    'cerrado': 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
    'default': 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
};
// ===== FIN: CORRECCIÓN DE ESTADO =====

// ===== INICIO: LÓGICA DE EDICIÓN CORREGIDA (¡AQUÍ ESTÁ EL CAMBIO!) =====
const puedeEditar = computed(() => {
    // 1. Si el usuario no tiene el permiso base 'update', no puede editar.
    if (!props.can.update) {
        return false;
    }
    
    // 2. Si el usuario es 'admin', SIEMPRE puede editar (ignora el bloqueo).
    if (user?.tipo_usuario === 'admin') {
        return true;
    }

    // 3. Si es cualquier otro rol, solo puede editar si el caso NO está bloqueado.
    return !props.caso.bloqueado;
});
// ===== FIN: LÓGICA DE EDICIÓN CORREGIDA =====

const docsGenerados = computed(() =>
    [...(props.caso.documentos_generados || [])].sort(
        (a, b) => new Date(b.created_at) - new Date(a.created_at)
    )
);
const adjuntos = computed(() =>
    // --- MODIFICADO: Ahora ordena por fecha_carga DESC (más nuevos primero) ---
    [...(props.caso.documentos || [])].sort(
        (a, b) => new Date(b.fecha_carga) - new Date(a.fecha_carga)
    )
);

const parseMoney = (input) => {
    let s = String(input ?? '').trim();
    if (!s) return 0;
    s = s.replace(/\s/g, '');
    const hasComma = s.includes(',');
    const hasDot = s.includes('.');

    if (hasComma && hasDot) {
        // Decide separador decimal por el último símbolo
        const decimalSep = s.lastIndexOf(',') > s.lastIndexOf('.') ? ',' : '.';
        const thousandSep = decimalSep === ',' ? '.' : ',';
        s = s.replace(new RegExp('\\' + thousandSep, 'g'), '');
        if (decimalSep === ',') s = s.replace(',', '.');
    } else if (hasComma) {
        // Coma como decimal
        s = s.replace(/\./g, '');
        s = s.replace(',', '.');
    } else {
        // Punto como decimal o sin decimales
        s = s.replace(/,/g, '');
    }

    const n = Number(s);
    return Number.isFinite(n) ? n : 0;
};

const formatCurrency = (value) =>
    new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 })
    .format(parseMoney(value));

// ===== COMPUTEDS 'totalPagado' Y 'saldoPendiente' ELIMINADOS =====
// (Ahora usamos 'props.resumen_financiero' que viene del controller)
// =================================================================

// --- INICIO: Lógica para formulario de Actuaciones ---
const actuacionForm = useForm({
    nota: '',
    fecha_actuacion: today
})

const guardarActuacion = () => {
    actuacionForm.post(route('casos.actuaciones.store', props.caso.id), {
        preserveScroll: true,
        onSuccess: () => {
            actuacionForm.reset()
            actuacionForm.fecha_actuacion = today
            router.reload({ only: ['actuaciones', 'caso'], preserveState: true }) // Recargar 'caso' también
        },
        onError: (errors) => {
             console.error("Error al guardar actuación:", errors);
        }
    })
}
// --- FIN: Lógica para formulario de Actuaciones ---

// --- INICIO: Lógica para Editar/Eliminar Actuación ---
const editActuacionModalAbierto = ref(false)
const actuacionEnEdicion = ref(null)

const editActuacionForm = useForm({
    nota: '',
    fecha_actuacion: '',
})

const abrirModalEditar = (actuacion) => {
    actuacionEnEdicion.value = actuacion
    editActuacionForm.nota = actuacion.nota
    editActuacionForm.fecha_actuacion = actuacion.fecha_actuacion ? String(actuacion.fecha_actuacion).split('T')[0] : ''
    editActuacionModalAbierto.value = true
}

const cerrarModalEditar = () => {
    editActuacionModalAbierto.value = false
    actuacionEnEdicion.value = null
    editActuacionForm.reset()
}

const actualizarActuacion = () => {
    if (!actuacionEnEdicion.value) return;

    editActuacionForm.put(route('casos.actuaciones.update', actuacionEnEdicion.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            cerrarModalEditar()
            router.reload({ only: ['actuaciones', 'caso'], preserveState: true }) // Recargar 'caso' también
        },
        onError: (errors) => {
             console.error("Error al actualizar actuación:", errors);
        }
    })
}

const eliminarActuacion = (actuacionId) => {
    if (confirm('¿Estás seguro de que quieres eliminar esta actuación? Esta acción no se puede deshacer.')) {
        router.delete(route('casos.actuaciones.destroy', actuacionId), {
            preserveScroll: true,
            onSuccess: () => {
                 router.reload({ only: ['actuaciones', 'caso'], preserveState: true }) // Recargar 'caso' también
            },
            onError: (errors) => {
                 console.error("Error al eliminar actuación:", errors);
                 alert("Error: No se pudo eliminar la actuación. Es posible que no tengas permiso.");
            }
        })
    }
}
// --- FIN: Lógica para Editar/Eliminar Actuación ---

// ===== INICIO: LÓGICA DE DESBLOQUEO =====
// (Se eliminó el computed 'canUnlock' porque la lógica se movió al v-if del botón)

const confirmUnlockCase = () => {
    if (confirm('¿Estás seguro de que quieres desbloquear este caso?')) {
        router.patch(route('casos.unlock', props.caso.id), {}, {
            preserveScroll: true,
            // onSuccess y onError no son estrictamente necesarios,
            // ya que Inertia recargará la página y el 'flash' (success/error)
            // se mostrará automáticamente en el header.
        });
    }
};
// ===== FIN: LÓGICA DE DESBLOQUEO =====

// --- INICIO: Lógica para desplegable de Codeudor ---
const codeudorAbiertoId = ref(null);

const toggleCodeudor = (id) => {
    if (codeudorAbiertoId.value === id) {
        codeudorAbiertoId.value = null; // Cierra si ya está abierto
    } else {
        codeudorAbiertoId.value = id; // Abre el nuevo
    }
};

// Helper para parsear JSON de forma segura (necesario para 'addresses')
const safeJsonParse = (jsonString) => {
    if (!jsonString) return [];
    if (typeof jsonString === 'object') return jsonString; // Ya es un array/objeto
    try {
        const parsed = JSON.parse(jsonString);
        return Array.isArray(parsed) ? parsed : [];
    } catch (e) {
        return []; // Devuelve array vacío si el JSON está mal formado
    }
};
// --- FIN: Lógica para desplegable de Codeudor ---

</script>

<template>
    <Head :title="'Ficha del Caso #' + caso.id" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-4">
                <div>
                    <Link
                        :href="route('casos.index')"
                        class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 flex items-center mb-1"
                    >
                        &larr; Volver a Gestión de Casos
                    </Link>
                    <h2 class="font-semibold text-2xl text-gray-800 dark:text-gray-200 leading-tight">
                        Ficha del Caso <span class="text-indigo-500">#{{ caso.id }}</span>
                        <span
                            v-if="caso.bloqueado"
                            class="ml-2 px-2 py-0.5 text-xs rounded bg-red-100 text-red-700 align-middle"
                        >Bloqueado</span>
                        
                        <!-- ===== INICIO: LÓGICA DE DESBLOQUEO CORREGIDA (¡AQUÍ ESTÁ EL CAMBIO!) ===== -->
                        <!-- Ahora solo el admin puede ver este botón -->
                        <button
                            v-if="caso.bloqueado && user?.tipo_usuario === 'admin'" 
                            @click="confirmUnlockCase"
                            class="ml-2 inline-flex items-center px-2.5 py-1 border border-transparent rounded-md shadow-sm text-xs font-medium text-yellow-800 bg-yellow-100 hover:bg-yellow-200 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2 transition ease-in-out duration-150 align-middle"
                            title="Desbloquear Caso"
                        >
                            <LockOpenIcon class="h-4 w-4 mr-1.5" />
                            Desbloquear
                        </button>
                        <!-- ===== FIN: LÓGICA DE DESBLOQUEO CORREGIDA ===== -->
                    </h2>
                </div>
                <div class="flex items-center space-x-2 flex-shrink-0">
                    <!-- El botón de Clonar usa la misma lógica de `puedeEditar` -->
                    <Link
                        v-if="puedeEditar"
                        :href="route('casos.clonar', caso.id)"
                        class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none disabled:opacity-25 transition ease-in-out duration-150"
                    >
                        Clonar
                    </Link>
                    <!-- El botón de Editar Caso ahora usa la nueva lógica `puedeEditar` -->
                    <Link
                        v-if="puedeEditar"
                        :href="route('casos.edit', caso.id)"
                        class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white active:bg-gray-900 dark:active:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                    >
                        Editar Caso
                    </Link>

                    <!-- ===== INICIO: NUEVO BOTÓN DE CONTRATO ===== -->
                    <!-- Muestra "Generar" si el contrato NO existe Y se puede editar -->
                    <Link
                        v-if="!caso.contrato && puedeEditar"
                        :href="route('honorarios.contratos.create', { caso_id: caso.id })"
                        class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-800 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                    >
                        Generar Contrato
                    </Link>
                    <!-- Muestra "Ver" si el contrato SÍ existe (sin importar si se puede editar) -->
                    <Link
                        v-else-if="caso.contrato"
                        :href="route('honorarios.contratos.show', caso.contrato.id)"
                        class="inline-flex items-center px-4 py-2 bg-green-100 border border-green-300 rounded-md font-semibold text-xs text-green-800 uppercase tracking-widest hover:bg-green-200 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:bg-green-900 dark:border-green-700 dark:text-green-200 dark:hover:bg-green-800 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                    >
                        Ver Contrato
                    </Link>
                    <!-- ===== FIN: NUEVO BOTÓN DE CONTRATO ===== -->

                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div
                    v-if="page.props.flash.success"
                    class="mb-6 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg shadow-md"
                    role="status"
                    aria-live="polite"
                >
                    <p class="font-bold">Éxito</p>
                    <p>{{ page.props.flash.success }}</p>
                </div>
                <div
                    v-if="page.props.flash.error"
                    class="mb-6 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg shadow-md"
                    role="alert"
                >
                    <p class="font-bold">Error</p>
                    <p>{{ page.props.flash.error }}</p>
                </div>

                <div class="bg-white dark:bg-gray-800 shadow-lg rounded-xl p-6 mb-8">
                    <div class="flex flex-col md:flex-row justify-between md:items-center gap-4">
                        <div class="flex-grow">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                Deudor Principal
                            </p>
                            <h1 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white">
                                {{ caso.deudor ? caso.deudor.nombre_completo : 'No Asignado' }}
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-300">
                                {{ caso.cooperativa ? caso.cooperativa.nombre : 'N/A' }}
                            </p>
                        </div>
                        <div class="flex items-center justify-end flex-wrap gap-4 mt-4 md:mt-0">
                            <div class="text-right">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Monto Total</p>
                                <p class="text-xl font-semibold text-gray-800 dark:text-gray-100">
                                    {{ formatCurrency(caso.monto_total) }}
                                </p>
                            </div>
                            <div
                                v-if="caso.dias_en_mora > 0"
                                class="text-right bg-red-50 dark:bg-red-900/50 p-3 rounded-lg"
                            >
                                <p class="text-sm font-medium text-red-600 dark:text-red-300">Días en Mora</p>
                                <p class="text-xl font-bold text-red-700 dark:text-red-200">{{ caso.dias_en_mora }}</p>
                            </div>
                            
                            <div class="text-right" v-if="caso.estado_proceso">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Estado</p>
                                <span
                                    class="px-3 py-1.5 inline-flex text-sm leading-5 font-semibold rounded-full capitalize"
                                    :class="statusProcessoClasses[caso.estado_proceso] || statusProcessoClasses['default']"
                                >{{ formatLabel(caso.estado_proceso) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md">
                    <div class="border-b border-gray-200 dark:border-gray-700">
                        <nav class="-mb-px flex space-x-6 overflow-x-auto px-6" aria-label="Tabs">
                            <button
                                @click="activeTab = 'resumen'"
                                :class="[
                                    activeTab === 'resumen'
                                        ? 'border-indigo-500 text-indigo-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:hover:text-gray-200 dark:hover:border-gray-500',
                                ]"
                                class="flex items-center whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200"
                            >
                                <InformationCircleIcon class="h-5 w-5 mr-2" /> Resumen
                            </button>
                            <button
                                @click="activeTab = 'documentos'"
                                :class="[
                                    activeTab === 'documentos'
                                        ? 'border-indigo-500 text-indigo-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:hover:text-gray-200 dark:hover:border-gray-500',
                                ]"
                                class="flex items-center whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200"
                            >
                                <FolderOpenIcon class="h-5 w-5 mr-2" /> Documentos
                            </button>
                            <button
                                @click="activeTab = 'financiero'"
                                :class="[
                                    activeTab === 'financiero'
                                        ? 'border-indigo-500 text-indigo-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:hover:text-gray-200 dark:hover:border-gray-500',
                                ]"
                                class="flex items-center whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200"
                            >
                                <CreditCardIcon class="h-5 w-5 mr-2" /> Financiero
                            </button>
                            <button
                                @click="activeTab = 'actuaciones'"
                                :class="[
                                    activeTab === 'actuaciones'
                                        ? 'border-indigo-500 text-indigo-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:hover:text-gray-200 dark:hover:border-gray-500',
                                ]"
                                class="flex items-center whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200"
                            >
                                <ClipboardDocumentListIcon class="h-5 w-5 mr-2" /> Actuaciones
                            </button>
                            <button
                                @click="activeTab = 'actividad'"
                                :class="[
                                    activeTab === 'actividad'
                                        ? 'border-indigo-500 text-indigo-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:hover:text-gray-200 dark:hover:border-gray-500',
                                ]"
                                class="flex items-center whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200"
                            >
                                <ClockIcon class="h-5 w-5 mr-2" /> Actividad y Logs
                            </button>
                        </nav>
                    </div>

                    <div class="p-6">
                        <div v-show="activeTab === 'resumen'" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            <div class="lg:col-span-2 space-y-6">
                                <div class="bg-gray-50 dark:bg-gray-900/50 p-6 rounded-lg">
                                    <h3 class="text-lg font-bold mb-4 flex items-center">
                                        <ScaleIcon class="h-6 w-6 mr-2 text-gray-500" />Detalles del Proceso
                                    </h3>

                                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                                        <div>
                                            <p class="text-sm text-gray-500">Numero De Pagare</p>
                                            <p class="font-semibold">{{ caso.referencia_credito || 'N/A' }}</p>
                                        </div>
                                        
                                        <div>
                                            <p class="text-sm text-gray-500">Número de Radicado</p>
                                            <p class="font-semibold">{{ caso.radicado || 'N/A' }}</p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-500">Especialidad</p>
                                            <p class="font-semibold">{{ formatLabel(caso.especialidad?.nombre) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-500">Tipo de Proceso</p>
                                            <p class="font-semibold">{{ formatLabel(caso.tipo_proceso) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-500">Proceso</p>
                                            <p class="font-semibold">{{ formatLabel(caso.subtipo_proceso) }}</p>
                                        </div>

                                        <!-- --- INICIO: CAMBIO L4 (AÑADIDO) --- -->
                                        <div>
                                            <p class="text-sm text-gray-500">Subproceso (Detalle)</p>
                                            <p class="font-semibold">{{ formatLabel(caso.subproceso) }}</p>
                                        </div>
                                        <!-- --- FIN: CAMBIO L4 --- -->

                                        <div>
                                            <p class="text-sm text-gray-500">Etapa Procesal</p>
                                            <p class="font-semibold">{{ formatLabel(caso.etapa_procesal) }}</p>
                                        </div>
                                        <div class="md:col-span-2">
                                            <p class="text-sm text-gray-500">Juzgado</p>
                                            <p class="font-semibold">{{ caso.juzgado ? caso.juzgado.nombre : 'No especificado' }}</p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-500">Fecha de Demanda</p>
                                            <p class="font-semibold">{{ formatDate(caso.fecha_apertura) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-500">Fecha de Vencimiento</p>
                                            <p class="font-semibold">{{ formatDate(caso.fecha_vencimiento) }}</p>
                                        </div>

                                        <div>
                                            <p class="text-sm text-gray-500">Tasa Interés Corriente (%)</p>
                                            <p class="font-semibold">{{ caso.tasa_interes_corriente ?? 'N/A' }} %</p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-500">Fecha Tasa Interés</p>
                                            <p class="font-semibold">{{ formatDate(caso.fecha_tasa_interes) }}</p>
                                        </div>
                                        <div class="md:col-span-2">
                                            <p class="text-sm text-gray-500">Tipo de Garantía</p>
                                            <p class="font-semibold capitalize">{{ caso.tipo_garantia_asociada }}</p>
                                        </div>
                                    </dl>
                                </div>

                                <div class="bg-gray-50 dark:bg-gray-900/50 p-6 rounded-lg">
                                    <CumplimientoLegal :validaciones="caso.validaciones_legales" />
                                </div>
                            </div>

                            <div class="lg:col-span-1 space-y-6">
                                <div class="bg-gray-50 dark:bg-gray-900/50 p-6 rounded-lg">
                                    <h3 class="text-lg font-bold mb-4">Partes Involucradas</h3>
                                    <ul class="space-y-4">
                                        <li class="flex items-center">
                                            <UserCircleIcon class="h-6 w-6 mr-3 text-indigo-500" />
                                            <div class="text-sm">
                                                <span class="font-medium text-gray-900 dark:text-white">Deudor:</span>
                                                {{ caso.deudor ? caso.deudor.nombre_completo : 'N/A' }}
                                            </div>
                                        </li>
                                        
                                        <li v-if="!caso.codeudores || caso.codeudores.length === 0" class="flex items-center">
                                            <UsersIcon class="h-6 w-6 mr-3 text-gray-400" />
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                No hay codeudores asignados.
                                            </div>
                                        </li>
                                        
                                        <li v-else v-for="(codeudor, index) in caso.codeudores" :key="codeudor.id" class="flex flex-col items-start w-full">
                                            <button @click="toggleCodeudor(codeudor.id)" type="button" class="flex items-center w-full text-left p-1 -ml-1 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                                <UsersIcon class="h-6 w-6 mr-3 text-gray-400 flex-shrink-0" />
                                                <div class="text-sm flex-grow">
                                                    <span class="font-medium text-gray-900 dark:text-white">Codeudor {{ index + 1 }}:</span>
                                                    {{ codeudor.nombre_completo }}
                                                    <span class="text-gray-500 dark:text-gray-400">({{ codeudor.numero_documento }})</span>
                                                </div>
                                                <ChevronDownIcon class="h-5 w-5 text-gray-400 transition-transform duration-200" :class="{ 'rotate-180': codeudorAbiertoId === codeudor.id }" />
                                            </button>
                                            
                                            <div v-show="codeudorAbiertoId === codeudor.id" class="w-full pl-9 pr-2 pt-3 pb-2 text-sm space-y-3">
                                                <div v-if="codeudor.celular" class="flex items-center">
                                                    <PhoneIcon class="h-4 w-4 mr-2 text-gray-400" />
                                                    <span class="text-gray-700 dark:text-gray-300">{{ codeudor.celular }}</span>
                                                </div>
                                                <div v-if="codeudor.correo" class="flex items-center">
                                                    <EnvelopeIcon class="h-4 w-4 mr-2 text-gray-400" />
                                                    <span class="text-gray-700 dark:text-gray-300 break-all">{{ codeudor.correo }}</span>
                                                </div>
                                                
                                                <div v-if="safeJsonParse(codeudor.addresses) && safeJsonParse(codeudor.addresses).length > 0">
                                                    <div v-for="(addr, idx) in safeJsonParse(codeudor.addresses)" :key="idx" class="flex items-start mt-2">
                                                        <MapPinIcon class="h-4 w-4 mr-2 text-gray-400 flex-shrink-0 mt-0.5" />
                                                        <div class="text-gray-700 dark:text-gray-300">
                                                            <span class="font-semibold">{{ addr.label || 'Dirección' }}:</span>
                                                            <span> {{ addr.address }}<span v-if="addr.city">, {{ addr.city }}</span></span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div v-else-if="!codeudor.celular && !codeudor.correo && (!safeJsonParse(codeudor.addresses) || safeJsonParse(codeudor.addresses).length === 0)" class="text-gray-400 italic">
                                                    No hay información de contacto adicional.
                                                </div>
                                            </div>
                                        </li>
                                        <li class="flex items-center">
                                            <UserCircleIcon class="h-6 w-6 mr-3 text-gray-400" />
                                            <div class="text-sm">
                                                <span class="font-medium text-gray-900 dark:text-white">Abogado:</span>
                                                {{ caso.user ? caso.user.name : 'N/A' }}
                                            </div>
                                        </li>
                                    </ul>
                                </div>

                                <PrimaryButton @click="abrirModalAlerta" class="w-full justify-center">
                                    <BellAlertIcon class="h-5 w-5 mr-2" />Programar Alerta
                                </PrimaryButton>
                            </div>
                        </div>

                        <div v-show="activeTab === 'documentos'" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <div class="bg-gray-50 dark:bg-gray-900/50 p-6 rounded-lg">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-lg font-bold flex items-center">
                                        <DocumentDuplicateIcon class="h-6 w-6 mr-2 text-indigo-500" />Documentos Generados
                                    </h3>
                                    <PrimaryButton v-if="puedeEditar" @click="abrirModalGenerar" class="!text-xs">
                                        Generar +
                                    </PrimaryButton>
                                </div>

                                <div
                                    v-if="!docsGenerados.length"
                                    class="text-center py-6 text-gray-500 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg"
                                >
                                    No hay documentos generados.
                                </div>

                                <ul v-else class="space-y-2">
                                    <li
                                        v-for="doc in docsGenerados"
                                        :key="doc.id"
                                        class="p-3 bg-white dark:bg-gray-800 rounded-md shadow-sm flex items-center justify-between"
                                    >
                                        <div class="flex items-center truncate">
                                            <LockClosedIcon
                                                v-if="doc.es_confidencial"
                                                class="h-5 w-5 text-yellow-500 mr-3 flex-shrink-0"
                                                title="Confidencial"
                                            />
                                            <div class="truncate">
                                                <p
                                                    class="text-sm font-medium text-gray-900 dark:text-white truncate"
                                                    :title="`${doc.nombre_base}.docx`"
                                                >
                                                    {{ doc.nombre_base }}.docx
                                                </p>
                                                <p class="text-xs text-gray-500">
                                                    Generado por {{ doc.usuario?.name || 'N/A' }} (v{{ doc.version_plantilla }})
                                                </p>
                                            </div>
                                        </div>
                                        <div class="flex items-center space-x-2 flex-shrink-0 ml-4">
                                            <a
                                                :href="route('documentos.descargar.docx', doc.id)"
                                                class="text-blue-600 hover:text-blue-800 p-1.5 rounded-full hover:bg-blue-100 dark:hover:bg-blue-900/50"
                                                title="Descargar .docx"
                                                aria-label="Descargar DOCX"
                                            >
                                                <ArrowDownTrayIcon class="h-5 w-5" />
                                            </a>
                                            <a
                                                :href="route('documentos.descargar.pdf', doc.id)"
                                                class="text-red-600 hover:text-red-800 p-1.5 rounded-full hover:bg-red-100 dark:hover:bg-red-900/50"
                                                title="Descargar .pdf"
                                                aria-label="Descargar PDF"
                                            >
                                                <ArrowDownTrayIcon class="h-5 w-5" />
                                            </a>
                                        </div>
                                    </li>
                                </ul>
                            </div>

                            <div class="bg-gray-50 dark:bg-gray-900/50 p-6 rounded-lg">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-lg font-bold">Documentos Adjuntos</h3>
                                    <PrimaryButton v-if="puedeEditar" @click="openUploadModal" class="!text-xs">
                                        Subir +
                                    </PrimaryButton>
                                </div>

                                <div
                                    v-if="!adjuntos.length"
                                    class="text-center py-6 text-gray-500 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg"
                                >
                                    No hay documentos adjuntos.
                                </div>

                                <!-- --- INICIO: LISTA DE ADJUNTOS MODIFICADA --- -->
                                <ul v-else class="space-y-3">
                                    <li
                                        v-for="doc in adjuntos"
                                        :key="doc.id"
                                        class="p-3 bg-white dark:bg-gray-800 rounded-md shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2"
                                    >
                                        <div class="flex-grow pr-4">
                                            <p class="text-sm font-medium text-gray-900 dark:text-white capitalize">
                                                {{ doc.tipo_documento }}
                                            </p>

                                            <!-- --- INICIO: CORRECCIÓN (MOSTRAR ASOCIACIÓN) --- -->
                                            <p class="text-xs text-indigo-700 dark:text-indigo-400 font-semibold">
                                                <UsersIcon class="h-3 w-3 inline-block -mt-0.5 mr-1" />
                                                <!-- Comprobamos si está asociado a 'persona' (Deudor) o 'codeudor' -->
                                                <span v-if="doc.persona">
                                                    Adjunto a Deudor: {{ doc.persona.nombre_completo }}
                                                </span>
                                                <span v-else-if="doc.codeudor">
                                                    Adjunto a Codeudor: {{ doc.codeudor.nombre_completo }}
                                                </span>
                                                <span v-else>
                                                    Adjunto al Caso
                                                </span>
                                            </p>
                                            <!-- --- FIN: CORRECCIÓN --- -->

                                            <!-- MODIFICADO: Mostrar quién subió -->
                                            <p class="text-xs text-gray-500">
                                                Subido el {{ formatDate(doc.fecha_carga) }}
                                            </p>

                                            <!-- NUEVO: Mostrar la nota si existe -->
                                            <div v-if="doc.nota" class="mt-2 flex items-start text-xs text-gray-600 dark:text-gray-400 border-l-2 border-gray-300 dark:border-gray-600 pl-2">
                                                <ChatBubbleLeftEllipsisIcon class="h-4 w-4 mr-1.5 flex-shrink-0 text-gray-400" />
                                                <span class="italic whitespace-pre-wrap">{{ doc.nota }}</span>
                                            </div>
                                        </div>

                                        <div class="flex items-center space-x-2 flex-shrink-0 w-full sm:w-auto justify-end">
                                            <a
                                                :href="route('documentos-caso.view', doc.id)"
                                                target="_blank"
                                                class="inline-flex items-center px-3 py-1 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none disabled:opacity-25 transition ease-in-out duration-150"
                                            >
                                                Ver
                                            </a>
                                            <DangerButton
                                                v-if="puedeEditar"
                                                @click="confirmDocumentDeletion(doc)"
                                                class="!py-2 !text-xs"
                                                aria-label="Eliminar adjunto"
                                            >
                                                <TrashIcon class="h-4 w-4" />
                                            </DangerButton>
                                        </div>
                                    </li>
                                </ul>
                                <!-- --- FIN: LISTA DE ADJUNTOS MODIFICADA --- -->
                            </div>
                        </div>

                        <!-- ===== INICIO: PESTAÑA FINANCIERO TOTALMENTE NUEVA ===== -->
                        <div v-show="activeTab === 'financiero'" class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div class="bg-gray-50 dark:bg-gray-900/50 p-4 rounded-lg text-center">
                                    <p class="text-sm text-gray-500">Monto Total (Contrato + Cargos)</p>
                                    <p class="text-2xl font-bold">{{ formatCurrency(resumen_financiero.monto_total) }}</p>
                                </div>
                                <div class="bg-green-50 dark:bg-green-900/50 p-4 rounded-lg text-center">
                                    <p class="text-sm text-green-700 dark:text-green-300">Total Pagado</p>
                                    <p class="text-2xl font-bold text-green-800 dark:text-green-200">
                                        {{ formatCurrency(resumen_financiero.total_pagado) }}
                                    </p>
                                </div>
                                <div class="bg-yellow-50 dark:bg-yellow-900/50 p-4 rounded-lg text-center">
                                    <p class="text-sm text-yellow-700 dark:text-yellow-300">Saldo Pendiente</p>
                                    <p class="text-2xl font-bold text-yellow-800 dark:text-yellow-200">
                                        {{ formatCurrency(resumen_financiero.saldo_pendiente) }}
                                    </p>
                                </div>
                            </div>

                            <div class="bg-gray-50 dark:bg-gray-900/50 p-6 rounded-lg text-center">
                                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Gestión Financiera</h3>
                                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                    Toda la gestión de pagos, cuotas y cargos se maneja directamente en el contrato.
                                </p>
                                <PrimaryButton v-if="caso.contrato" :href="route('honorarios.contratos.show', caso.contrato.id)" class="mt-4">
                                    Ir al Contrato #{{ caso.contrato.id }}
                                </PrimaryButton>
                                <p v-else class="mt-4 text-sm text-gray-500">
                                    Aún no se ha generado un contrato para este caso.
                                </p>
                            </div>
                        </div>
                        <!-- ===== FIN: PESTAÑA FINANCIERO TOTALMENTE NUEVA ===== -->


                        <div v-show="activeTab === 'actuaciones'" class="space-y-6">
                            <div class="bg-gray-50 dark:bg-gray-900/50 p-6 rounded-lg">
                                <fieldset :disabled="caso.bloqueado && user?.tipo_usuario !== 'admin'">
                                    <form @submit.prevent="guardarActuacion" class="mb-6 pb-6 border-b dark:border-gray-700">
                                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-2">Nueva Actuación Manual</h3>
                                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                                            <div class="md:col-span-3">
                                                <InputLabel for="actuacion_nota" value="Descripción de la Actuación" />
                                                <Textarea
                                                    id="actuacion_nota"
                                                    v-model="actuacionForm.nota"
                                                    rows="3"
                                                    class="mt-1 block w-full"
                                                    required
                                                />
                                                <InputError class="mt-2" :message="actuacionForm.errors.nota" />
                                            </div>
                                            <div>
                                                <InputLabel for="fecha_actuacion" value="Fecha de Actuación" />
                                                <TextInput
                                                    id="fecha_actuacion"
                                                    type="date"
                                                    v-model="actuacionForm.fecha_actuacion"
                                                    class="mt-1 block w-full"
                                                    required
                                                />
                                                <InputError class="mt-2" :message="actuacionForm.errors.fecha_actuacion" />
                                            </div>
                                        </div>
                                        <div class="flex justify-end">
                                            <PrimaryButton :disabled="actuacionForm.processing">
                                                {{ actuacionForm.processing ? 'Registrando...' : 'Registrar Actuación' }}
                                            </PrimaryButton>
                                        </div>
                                    </form>
                                </fieldset>

                                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4">Historial de Actuaciones</h3>
                                <div v-if="!props.actuaciones || props.actuaciones.length === 0" class="text-center py-8 text-gray-500 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg">
                                    No hay actuaciones registradas para este caso.
                                </div>
                                <div v-else class="space-y-4">
                                    <div v-for="actuacion in props.actuaciones" :key="actuacion.id" class="p-4 border rounded-lg bg-white dark:bg-gray-800 dark:border-gray-700">
                                        <p class="text-sm text-gray-800 dark:text-gray-200 whitespace-pre-wrap">{{ actuacion.nota }}</p>
                                        <div class="mt-2 text-xs text-gray-500 dark:text-gray-400 flex items-center justify-between">
                                            <span>
                                                Registrado por: {{ actuacion.user?.name ?? 'Usuario desconocido' }} el {{ formatDateTime(actuacion.created_at) }}
                                                <span v-if="actuacion.fecha_actuacion"> | Fecha Actuación: {{ fmtDateSimple(actuacion.fecha_actuacion) }}</span>
                                            </span>
                                            <div v-if="$page.props.auth.user && ['admin', 'gestor', 'abogado'].includes($page.props.auth.user.tipo_usuario)" class="flex-shrink-0 flex items-center gap-2">
                                                <button @click="abrirModalEditar(actuacion)" type="button" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300" title="Editar">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                                </button>
                                                <button @click="eliminarActuacion(actuacion.id)" type="button" class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300" title="Eliminar">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-show="activeTab === 'actividad'" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <div class="bg-gray-50 dark:bg-gray-900/50 p-6 rounded-lg">
                                <h3 class="text-lg font-bold mb-4 flex items-center">
                                    <ClockIcon class="h-6 w-6 mr-2 text-gray-500" />Bitácora de Actividad
                                </h3>
                                <div
                                    class="relative border-l-2 border-gray-200 dark:border-gray-700 ml-3 max-h-96 overflow-y-auto pr-2"
                                >
                                    <div v-if="!caso.bitacoras || !caso.bitacoras.length" class="pl-8 pb-4">
                                        <p class="text-sm text-gray-500">No hay actividades.</p>
                                    </div>
                                    <ol v-else class="space-y-6">
                                        <li v-for="item in caso.bitacoras" :key="item.id" class="relative pl-8">
                                            <div
                                                class="absolute -left-[7px] top-1 h-3 w-3 rounded-full bg-indigo-500 ring-4 ring-gray-50 dark:ring-gray-900/50"
                                            ></div>
                                            <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                                                {{ item.accion }}
                                            </p>
                                            <p v-if="item.comentario" class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                                {{ item.comentario }}
                                            </p>
                                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1.5">
                                                Por <span class="font-medium">{{ item.user ? item.user.name : 'N/A' }}</span>
                                            </p>
                                            <p class="text-xs text-gray-400 dark:text-gray-500">
                                                {{ formatDateTime(item.created_at) }}
                                            </p>
                                        </li>
                                    </ol>
                                </div>
                            </div>

                            <div class="bg-gray-50 dark:bg-gray-900/50 p-6 rounded-lg">
                                <HistorialAuditoria :eventos="caso.auditoria" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- --- INICIO: MODAL DE SUBIDA MODIFICADO --- -->
        <Modal :show="confirmingDocumentUpload" @close="closeUploadModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Subir Nuevo Documento Adjunto
                </h2>

                <form @submit.prevent="submitDocument" class="mt-6 space-y-6">
                    <div>
                        <InputLabel for="tipo_documento_upload" value="Tipo de Documento *" />
                        <select
                            v-model="docForm.tipo_documento"
                            id="tipo_documento_upload"
                            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm"
                        >
                            <option>pagaré</option>
                            <option>carta instrucciones</option>
                            <option>certificación saldo</option>
                            <option>libranza</option>
                            <option>cédula deudor</option>
                            <option>cédula codeudor</option>
                            <option>otros</option>
                        </select>
                        <InputError :message="docForm.errors.tipo_documento" class="mt-2" />
                    </div>

                    <!-- --- INICIO: CORRECCIÓN (POLIMORFISMO) --- -->
                    <div>
                        <InputLabel for="asociado_a_upload" value="Asociar a *" />
                        <SelectInput
                            v-model="docForm.asociado_a"
                            id="asociado_a_upload"
                            class="mt-1 block w-full"
                        >
                            <!-- Opción 1: El Deudor Principal (Persona) -->
                            <option v-if="caso.deudor" :value="`persona-${caso.deudor.id}`">
                                Deudor Principal: {{ caso.deudor.nombre_completo }}
                            </option>
                            <!-- O si no hay deudor, se asocia solo al caso -->
                            <option v-else :value="null">
                                Al Caso (Sin Deudor Asignado)
                            </option>
                            
                            <!-- Opción 2: Los Codeudores -->
                            <option v-for="codeudor in caso.codeudores" 
                                    :key="codeudor.id" 
                                    :value="`codeudor-${codeudor.id}`"
                            >
                                Codeudor: {{ codeudor.nombre_completo }}
                            </option>
                        </SelectInput>
                        <!-- El error ahora reporta sobre 'asociado_a' -->
                        <InputError :message="docForm.errors.asociado_a" class="mt-2" />
                    </div>
                    <!-- --- FIN: CORRECCIÓN --- -->

                    <div>
                        <InputLabel for="fecha_carga" value="Fecha de Carga *" />
                        <TextInput
                            v-model="docForm.fecha_carga"
                            id="fecha_carga"
                            type="date"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError :message="docForm.errors.fecha_carga" class="mt-2" />
                    </div>

                    <!-- NUEVO: Nota del Documento -->
                    <div>
                        <InputLabel for="nota_upload" value="Nota (Opcional)" />
                        <Textarea
                            v-model="docForm.nota"
                            id="nota_upload"
                            rows="3"
                            class="mt-1 block w-full"
                            placeholder="Añada una breve descripción o contexto sobre este archivo..."
                        />
                        <InputError :message="docForm.errors.nota" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="archivo_upload" value="Archivo (Máx 128MB) *" />
                        <input
                            id="archivo_upload"
                            type="file"
                            @input="docForm.archivo = $event.target.files[0]"
                            class="mt-1 block w-full text-sm text-gray-500
                                    file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0
                                    file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                            accept=".pdf,.doc,.docx,image/*"
                        />
                        <InputError :message="docForm.errors.archivo" class="mt-2" />
                        <div v-if="docFormProgress !== null" class="mt-2 w-full bg-gray-200 rounded h-2">
                            <div class="h-2 rounded bg-indigo-600" :style="{ width: docFormProgress + '%' }"></div>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <SecondaryButton @click="closeUploadModal">Cancelar</SecondaryButton>
                        <PrimaryButton class="ms-3" :class="{ 'opacity-25': docForm.processing }" :disabled="docForm.processing">
                            Guardar Documento
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
        <!-- --- FIN: MODAL DE SUBIDA MODIFICADO --- -->


        <Modal :show="confirmingDocumentDeletion" @close="closeDeleteModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">¿Eliminar Documento de Prueba?</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400" v-if="documentToDelete">
                    ¿Estás seguro de que quieres eliminar permanentemente el documento:
                    <span class="font-medium">{{ documentToDelete.tipo_documento }}</span>?
                </p>
                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="closeDeleteModal">Cancelar</SecondaryButton>
                    <DangerButton class="ms-3" @click="deleteDocument">Eliminar Documento</DangerButton>
                </div>
            </div>
        </Modal>

        <Modal :show="mostrandoModalGenerar" @close="cerrarModalGenerar">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Generar Documento desde Plantilla</h2>
                <form @submit.prevent="submitGenerarDocumento" class="mt-6 space-y-6">
                    <div>
                        <InputLabel for="plantilla_id_generate" value="Plantilla a utilizar" />
                        <SelectInput
                            id="plantilla_id_generate"
                            class="mt-1 block w-full"
                            v-model="generarDocForm.plantilla_id"
                            required
                        >
                            <option disabled :value="null">-- Seleccione una plantilla --</option>
                            <option v-for="plantilla in plantillas" :key="plantilla.id" :value="plantilla.id">
                                {{ plantilla.nombre }} (v{{ plantilla.version }})
                            </option>
                        </SelectInput>
                        <InputError class="mt-2" :message="generarDocForm.errors.plantilla_id" />
                    </div>

                    <div>
                        <InputLabel for="observaciones_generate" value="Observaciones (Opcional)" />
                        <Textarea
                            id="observaciones_generate"
                            class="mt-1 block w-full"
                            v-model="generarDocForm.observaciones"
                            rows="3"
                        />
                        <InputError class="mt-2" :message="generarDocForm.errors.observaciones" />
                    </div>

                    <div class="flex items-start">
                        <div class="flex items-center h-5">
                            <Checkbox id="es_confidencial_generate" v-model:checked="generarDocForm.es_confidencial" />
                        </div>
                        <div class="ml-3 text-sm">
                            <InputLabel for="es_confidencial_generate" value="Documento Confidencial" />
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Si se marca, no será visible para usuarios con rol de cliente.
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <SecondaryButton @click="cerrarModalGenerar">Cancelar</SecondaryButton>
                        <PrimaryButton
                            class="ms-3"
                            :class="{ 'opacity-25': generarDocForm.processing }"
                            :disabled="generarDocForm.processing"
                        >
                            Generar Documento
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- ===== MODAL DE PAGO ELIMINADO ===== -->

        <Modal :show="mostrandoModalAlerta" @close="cerrarModalAlerta">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Programar Alerta Manual</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Cree un recordatorio para usted mismo sobre este caso. La alerta aparecerá en su bandeja de
                    notificaciones.
                </p>
                <form @submit.prevent="submitAlerta" class="mt-6 space-y-6">
                    <div>
                        <InputLabel for="mensaje_alerta" value="Mensaje del Recordatorio" />
                        <Textarea v-model="alertaForm.mensaje" id="mensaje_alerta" class="mt-1 block w-full" required rows="4" />
                        <InputError :message="alertaForm.errors.mensaje" class="mt-2" />
                    </div>
                    <InputLabel for="prioridad" value="Prioridad" />
                        <SelectInput id="prioridad" v-model="alertaForm.prioridad" class="mt-1 block w-full">
                            <option value="baja">Baja</option>
                            <option value="media">Media</option>
                            <option value="alta">Alta</option>
                        </SelectInput>
                    <InputError :message="alertaForm.errors.prioridad" class="mt-2" />
                    <div>
                        <InputLabel for="programado_para" value="Fecha de la Alerta (Opcional)" />
                        <TextInput
                            v-model="alertaForm.programado_para"
                            id="programado_para"
                            type="datetime-local"
                            class="mt-1 block w-full"
                        />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Si no selecciona fecha, la alerta se creará inmediatamente.
                        </p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                S te enviaremos un recordatorio diario hasta esta fecha y hora (zona horaria del sistema).
                        </p>
                        <InputError :message="alertaForm.errors.programado_para" class="mt-2" />
                    </div>
                    <div class="mt-6 flex justify-end">
                        <SecondaryButton @click="cerrarModalAlerta">Cancelar</SecondaryButton>
                        <PrimaryButton class="ms-3" :class="{ 'opacity-25': alertaForm.processing }" :disabled="alertaForm.processing">
                            Programar Alerta
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="editActuacionModalAbierto" @close="cerrarModalEditar">
            <form @submit.prevent="actualizarActuacion" class="p-6">
                 <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Editar Actuación Manual
                 </h2>

                 <div class="mt-6 space-y-4">
                       <div>
                         <InputLabel for="edit_actuacion_nota" value="Descripción de la Actuación" />
                         <Textarea
                             id="edit_actuacion_nota"
                             v-model="editActuacionForm.nota"
                             rows="4"
                             class="mt-1 block w-full"
                             required
                         />
                         <InputError class="mt-2" :message="editActuacionForm.errors.nota" />
                       </div>
                       <div>
                         <InputLabel for="edit_fecha_actuacion" value="Fecha de Actuación" />
                         <TextInput
                             id="edit_fecha_actuacion"
                             type="date"
                             v-model="editActuacionForm.fecha_actuacion"
                             class="mt-1 block w-full"
                             required
                         />
                         <InputError class="mt-2" :message="editActuacionForm.errors.fecha_actuacion" />
                       </div>
                 </div>

                 <div class="mt-6 flex justify-end gap-3">
                      <SecondaryButton type="button" @click="cerrarModalEditar"> Cancelar </SecondaryButton>
                      <PrimaryButton type="submit" :disabled="editActuacionForm.processing">
                           {{ editActuacionForm.processing ? 'Guardando...' : 'Guardar Cambios' }}
                      </PrimaryButton>
                 </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>

