<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import TextInput from '@/Components/TextInput.vue';
import Textarea from '@/Components/Textarea.vue';
import Checkbox from '@/Components/Checkbox.vue';
// --- INICIO: CAMBIOS DE UI ---
import { Head, Link, useForm, usePage, useRemember } from '@inertiajs/vue3'; // Importar useRemember
import { ref, computed, watch } from 'vue';
import vSelect from 'vue-select';
import 'vue-select/dist/vue-select.css';
import axios from 'axios';
// Importamos los iconos
import { 
    TrashIcon,
    InformationCircleIcon, 
    ScaleIcon, 
    UsersIcon, 
    LockClosedIcon, 
    PlusIcon, 
    MapPinIcon, 
    LinkIcon,
    ChevronUpIcon,
    ChevronDownIcon,
} from '@heroicons/vue/24/outline';
// --- FIN: CAMBIOS DE UI ---


// --- DEFINICIÓN DE PROPS ---
const props = defineProps({
    caso: { type: Object, required: true },
    estructuraProcesal: { type: Array, default: () => [] }, // <-- Esto ahora debe venir con L4
    etapas_procesales: { type: Array, default: () => [] },
});

// --- LÓGICA DE PESTAÑAS ---
// Usamos useRemember para que la pestaña se mantenga al recargar
const activeTab = useRemember('info-principal', 'casoEditTab:' + props.caso.id);

const user = usePage().props.auth.user;

// --- ESTADO DEL CASO (CERRADO/ACTIVO) ---
const isClosed = computed(() => props.caso.estado_proceso === 'cerrado');

// --- LÓGICA PARA EL MODAL DE CIERRE ---
const showCloseModal = ref(false);
const closeForm = useForm({ nota_cierre: '' });
const openCloseModal = () => {
    closeForm.reset();
    showCloseModal.value = true;
};
const closeTheCase = () => {
    closeForm.patch(route('casos.close', props.caso.id), {
        preserveScroll: true,
        onSuccess: () => {
            showCloseModal.value = false;
            closeForm.reset();
        },
    });
};

// --- LÓGICA PARA REABRIR CASO ---
const showReopenModal = ref(false);
const reopenForm = useForm({});
const openReopenModal = () => (showReopenModal.value = true);
const reopenTheCase = () => {
    reopenForm.patch(route('casos.reopen', props.caso.id), {
        preserveScroll: true,
        onSuccess: () => (showReopenModal.value = false),
    });
};

// --- LÓGICA REUTILIZABLE PARA BÚSQUEDA ASÍNCRONA ---
const useDebouncedSearch = (routeName, mapResult = (item) => item) => {
    const options = ref([]);
    const message = ref('Escribe para buscar...');
    let searchTimeout = null;

    const onSearch = (search, loading) => {
        clearTimeout(searchTimeout);
        if (search.length < 3) {
            if (options.value.length > 0 && !search) return;
            options.value = [];
            message.value = 'Escribe al menos 3 caracteres...';
            return;
        }
        loading(true);
        message.value = 'Buscando...';

        searchTimeout = setTimeout(() => {
            try {
                const url = route(routeName, { q: search });
                axios.get(url)
                    .then(response => {
                        if (Array.isArray(response.data)) {
                            const mappedOptions = response.data.map(mapResult).filter(Boolean);
                            options.value = mappedOptions;
                            message.value = mappedOptions.length === 0 ? 'No se encontraron resultados.' : '';
                        } else {
                            options.value = [];
                            message.value = 'Error en la respuesta de la API.';
                        }
                    })
                    .catch(error => {
                        console.error(`Error buscando en ${routeName}:`, error);
                        options.value = [];
                        message.value = 'Ocurrió un error al buscar.';
                    })
                    .finally(() => loading(false));
            } catch (routeError) {
                console.error(`Error de Ziggy: La ruta '${routeName}' no está definida.`, routeError);
                options.value = [];
                message.value = 'Error de configuración en la ruta.';
            }
        }, 500);
    };

    return { options, onSearch, message };
};

// --- TRANSFORMADORES DE DATOS DE API ---
const mapGeneric = (item) => item ? ({ id: item.id, nombre: item.label || item.nombre }) : null;
const mapUser = (item) => item ? ({ id: item.id, name: item.label || item.name }) : null;
const mapPersonas = (item) => {
    if (!item) return null;
    if (item.nombre_completo && item.numero_documento) return item;
    if (typeof item.label === 'string') {
        const match = item.label.match(/(.*) \((\S+)\)$/);
        if (match) return { id: item.id, nombre_completo: match[1].trim(), numero_documento: match[2] };
    }
    return { id: item.id, nombre_completo: item.label || item.nombre_completo || 'Dato no válido', numero_documento: 'N/A' };
};

// --- CONFIGURACIÓN DE SELECTORES ---
const { options: cooperativasOptions, onSearch: searchCooperativas, message: cooperativasMessage } = useDebouncedSearch('cooperativas.search', mapGeneric);
const { options: abogadosOptions, onSearch: searchAbogados, message: abogadosMessage } = useDebouncedSearch('users.search', mapUser);
const { options: juzgadosOptions, onSearch: searchJuzgados, message: juzgadosMessage } = useDebouncedSearch('juzgados.search', mapGeneric);
const { options: personasOptions, onSearch: searchPersonas, message: personasMessage } = useDebouncedSearch('personas.search', mapPersonas);


// ===== INICIO: FUNCIÓN HELPER PARA FECHAS (RESTAURADA) =====
/**
 * Formatea una fecha (desde cualquier formato) a 'YYYY-MM-DD' para los inputs.
 */
const formatDateForInput = (dateString) => {
    if (!dateString) return null;
    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) { 
            return null;
        }
        const year = date.getUTCFullYear();
        const month = (date.getUTCMonth() + 1).toString().padStart(2, '0');
        const day = date.getUTCDate().toString().padStart(2, '0');
        return `${year}-${month}-${day}`;
    } catch (e) {
        console.error("Error formateando fecha:", dateString, e);
        return null;
    }
};
// ===== FIN: FUNCIÓN HELPER PARA FECHAS =====


// --- LÓGICA "ANTI-JSON" PARA CODEUDORES ---
const safeJsonParse = (jsonString) => {
    if (!jsonString) return [];
    if (typeof jsonString === 'object') return jsonString; // Ya es un array
    try {
        const parsed = JSON.parse(jsonString);
        return Array.isArray(parsed) ? parsed : [];
    } catch (e) {
        return [];
    }
};

// --- INICIALIZACIÓN DEL FORMULARIO ---
const form = useForm({
    _method: 'PATCH',
    cooperativa_id: props.caso.cooperativa_id,
    user_id: props.caso.user_id,
    deudor_id: props.caso.deudor_id,
    
    // --- Codeudores con JSON parseado ---
    codeudores: props.caso.codeudores?.map(c => ({
        id: c.id, 
        nombre_completo: c.nombre_completo || '',
        tipo_documento: c.tipo_documento || 'CC',
        numero_documento: c.numero_documento || '',
        celular: c.celular || '',
        correo: c.correo || '',
        addresses: safeJsonParse(c.addresses), // Parsea a array
        social_links: safeJsonParse(c.social_links), // Parsea a array
        showDetails: ref(true), // Estado de UI
    })) || [],

    juzgado_id: props.caso.juzgado_id,
    referencia_credito: props.caso.referencia_credito,
    especialidad_id: props.caso.especialidad_id,
    tipo_proceso: props.caso.tipo_proceso,
    subtipo_proceso: props.caso.subtipo_proceso || null,
    subproceso: props.caso.subproceso || null, // <-- CAMPO L4 MANTENIDO
    etapa_procesal: props.caso.etapa_procesal || null,
    
    radicado: props.caso.radicado ?? '',
    tipo_garantia_asociada: props.caso.tipo_garantia_asociada ?? null,
    origen_documental: props.caso.origen_documental ?? null,
    medio_contacto: props.caso.medio_contacto ?? null,

    estado_proceso: props.caso.estado_proceso,
    
    // ===== INICIO: FECHAS CORREGIDAS (RESTAURADO) =====
    fecha_apertura: formatDateForInput(props.caso.fecha_apertura),
    fecha_vencimiento: formatDateForInput(props.caso.fecha_vencimiento),
    fecha_tasa_interes: formatDateForInput(props.caso.fecha_tasa_interes),
    // ===== FIN: FECHAS CORREGIDAS =====

    monto_total: props.caso.monto_total,
    tasa_interes_corriente: props.caso.tasa_interes_corriente,
    etapa_actual: props.caso.etapa_actual,
    bloqueado: !!props.caso.bloqueado,
    motivo_bloqueo: props.caso.motivo_bloqueo ?? '',
    notas_legales: props.caso.notas_legales,
});

// --- LÓGICA DE CODEUDORES DINÁMICOS (Anti-JSON) ---
const nuevoCodeudorTemplate = () => ({
    id: null,
    nombre_completo: '',
    tipo_documento: 'CC',
    numero_documento: '',
    celular: '',
    correo: '',
    addresses: [], // Inicializa como array nativo
    social_links: [], // Inicializa como array nativo
    showDetails: ref(true),
});

const addCodeudor = () => {
    form.codeudores.push(nuevoCodeudorTemplate());
    activeTab.value = 'codeudores'; // Cambiar a la pestaña de codeudores
};

const removeCodeudor = (index) => {
    form.codeudores.splice(index, 1);
};

// --- Nuevos helpers para Direcciones y Redes Sociales ---
const newAddressTemplate = () => ({ label: 'Casa', address: '', city: '' });
const newSocialTemplate = () => ({ type: 'Facebook', url: '' });

const addAddress = (codeudor) => {
    codeudor.addresses.push(newAddressTemplate());
};
const removeAddress = (codeudor, addrIndex) => {
    codeudor.addresses.splice(addrIndex, 1);
};
const addSocial = (codeudor) => {
    codeudor.social_links.push(newSocialTemplate());
};
const removeSocial = (codeudor, socialIndex) => {
    codeudor.social_links.splice(socialIndex, 1);
};
// --- FIN: LÓGICA DE FORMULARIO MEJORADA ---


// --- LÓGICA ESPECIAL DEL FORMULARIO DE EDICIÓN ---
// ===== INICIO: LÓGICA DE DESHABILITACIÓN CORREGIDA =====
const isFormDisabled = computed(() => {
    const isAdmin = user.tipo_usuario === 'admin';
    if (isAdmin) {
        return false; // El admin NUNCA se deshabilita
    }
    
    // Para otros usuarios, deshabilitar si está bloqueado O cerrado
    return props.caso.bloqueado || isClosed.value;
});
// ===== FIN: LÓGICA DE DESHABILITACIÓN CORREGIDA =====
watch(() => form.bloqueado, v => { if (!v) form.motivo_bloqueo = ''; });

// --- LÓGICA PARA SELECTORES EN CASCADA (ACTUALIZADO CON L4 Y CORREGIDO) ---
const formatLabel = (text) => {
    if (!text) return '';
    // --- INICIO: CORRECCIÓN ---
    // Esta función ahora maneja tanto "ACCION_DE_TUTELA"
    // como "Resolución/Incumplimiento de contrato"
    if (!text.includes('_') && (text.includes(' ') || text.includes('/'))) {
         // Asume que ya está formateado si tiene espacios/slash y no guiones bajos
        return text;
    }
    // --- FIN: CORRECCIÓN ---
    return text.replace(/_/g, ' ')
           .toLowerCase()
           .replace(/\b\w/g, char => char.toUpperCase());
};

const especialidades = computed(() => props.estructuraProcesal);

const tiposDisponibles = ref([]);
const subtiposDisponibles = ref([]);
const subprocesosDisponibles = ref([]); // <-- AÑADIDO

// --- INICIO: WATCHER L1 (CORREGIDO) ---
watch(() => form.especialidad_id, (newId) => {
    // 1. Resetear el VALOR de todos los niveles inferiores
    if (newId !== props.caso.especialidad_id) {
        form.tipo_proceso = null;
        form.subtipo_proceso = null;
        form.subproceso = null;
    }
    
    // 2. Resetear las LISTAS de todos los niveles inferiores
    tiposDisponibles.value = [];
    subtiposDisponibles.value = [];     // <-- ¡CLAVE! Limpia la lista L3
    subprocesosDisponibles.value = []; // <-- ¡CLAVE! Limpia la lista L4

    // 3. Poblar la lista L2
    if (newId) {
        const esp = especialidades.value.find(e => e.id === newId);
        tiposDisponibles.value = esp ? esp.tipos_proceso : [];
    }
}, { immediate: true });
// --- FIN: WATCHER L1 ---

// --- INICIO: WATCHER L2 (CORREGIDO) ---
watch(() => form.tipo_proceso, (newNombre) => {
    // 1. Resetear el VALOR de los niveles inferiores
    if (newNombre !== props.caso.tipo_proceso) {
        form.subtipo_proceso = null;
        form.subproceso = null;
    }

    // 2. Resetear las LISTAS de los niveles inferiores
    subtiposDisponibles.value = [];
    subprocesosDisponibles.value = []; // <-- ¡CLAVE! Limpia la lista L4

    // 3. Poblar la lista L3
    if (newNombre) {
        const tipo = tiposDisponibles.value.find(t => t.nombre === newNombre);
        subtiposDisponibles.value = tipo ? tipo.subtipos : [];
    }
}, { immediate: true });
// --- FIN: WATCHER L2 ---

// --- INICIO: WATCHER L3 (CORREGIDO) ---
watch(() => form.subtipo_proceso, (newNombre) => {
    // 1. Resetear el VALOR del nivel inferior
    if (newNombre !== props.caso.subtipo_proceso) {
        form.subproceso = null;
    }

    // 2. Resetear la LISTA del nivel inferior
    subprocesosDisponibles.value = [];

    // 3. Poblar la lista L4
    if (newNombre) {
        // Lógica de hidratación robusta para L4
        if (subtiposDisponibles.value && subtiposDisponibles.value.length > 0) {
            const subtipo = subtiposDisponibles.value.find(s => s.nombre === newNombre);
            subprocesosDisponibles.value = (subtipo && Array.isArray(subtipo.subprocesos)) ? subtipo.subprocesos : [];
        } 
        else if (form.tipo_proceso && tiposDisponibles.value.length > 0) {
            const currentTipo = tiposDisponibles.value.find(t => t.nombre === form.tipo_proceso);
            if (currentTipo && currentTipo.subtipos) {
                subtiposDisponibles.value = currentTipo.subtipos;
                const subtipo = subtiposDisponibles.value.find(s => s.nombre === newNombre);
                subprocesosDisponibles.value = (subtipo && Array.isArray(subtipo.subprocesos)) ? subtipo.subprocesos : [];
            }
        }
        else if (form.especialidad_id && especialidades.value.length > 0) {
             const currentEsp = especialidades.value.find(e => e.id === form.especialidad_id);
             if (currentEsp && currentEsp.tipos_proceso) {
                 tiposDisponibles.value = currentEsp.tipos_proceso;
                 const currentTipo = tiposDisponibles.value.find(t => t.nombre === form.tipo_proceso);
                 if (currentTipo && currentTipo.subtipos) {
                     subtiposDisponibles.value = currentTipo.subtipos;
                     const subtipo = subtiposDisponibles.value.find(s => s.nombre === newNombre);
                     subprocesosDisponibles.value = (subtipo && Array.isArray(subtipo.subprocesos)) ? subtipo.subprocesos : [];
                 }
             }
        }
    }
}, { immediate: true });
// --- FIN: WATCHER L3 ---


// --- VARIABLES Y LÓGICA PARA V-SELECT ---
const selectedCooperativa = ref(null);
const selectedAbogado = ref(null);
const selectedDeudor = ref(null);
const selectedJuzgado = ref(null);


watch(selectedCooperativa, (newValue) => form.cooperativa_id = newValue?.id ?? null);
watch(selectedAbogado, (newValue) => form.user_id = newValue?.id ?? null);
watch(selectedDeudor, (newValue) => form.deudor_id = newValue?.id ?? null);
watch(selectedJuzgado, (newValue) => form.juzgado_id = newValue?.id ?? null);

const initSelectData = () => {
    if (props.caso.cooperativa) {
        selectedCooperativa.value = {id: props.caso.cooperativa.id, nombre: props.caso.cooperativa.nombre};
        cooperativasOptions.value = [selectedCooperativa.value];
    }
    if (props.caso.user) {
        selectedAbogado.value = {id: props.caso.user.id, name: props.caso.user.name};
        abogadosOptions.value = [selectedAbogado.value];
    }
    if (props.caso.juzgado) {
        selectedJuzgado.value = {id: props.caso.juzgado.id, nombre: props.caso.juzgado.nombre};
        juzgadosOptions.value = [selectedJuzgado.value];
    }

    const initialPersonas = [];
    if (props.caso.deudor) {
        selectedDeudor.value = mapPersonas(props.caso.deudor);
        initialPersonas.push(selectedDeudor.value);
    }
    personasOptions.value = [...new Map(initialPersonas.filter(Boolean).map(item => [item.id, item])).values()];
};

initSelectData();

// --- ENVÍO DEL FORMULARIO ---
const submit = () => {
    // CAMBIO: Transformamos los datos ANTES de enviarlos.
    form.transform(data => ({
        ...data,
        codeudores: data.codeudores.map(c => ({
            ...c,
            // Convertir de nuevo a JSON string para el backend
            addresses: JSON.stringify(c.addresses || []),
            social_links: JSON.stringify(c.social_links || [])
        }))
    })).patch(route('casos.update', props.caso.id));
};
</script>

<template>
    <Head :title="'Editar Caso #' + caso.id" />

    <AuthenticatedLayout>
        <template #header>
             <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                 <div>
                     <div class="flex items-center gap-3">
                         <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                             Editando Caso <span class="text-indigo-500">#{{ caso.id }}</span>
                         </h2>
                         <span v-if="caso.bloqueado" class="px-2 py-0.5 text-xs rounded bg-red-100 text-red-700 font-medium">Bloqueado</span>
                         <span class="px-2 py-0.5 text-xs rounded font-bold capitalize" :class="{
                             'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300': !isClosed,
                             'bg-gray-200 text-gray-800 dark:bg-gray-600 dark:text-gray-200': isClosed,
                         }">
                             {{ caso.estado_proceso }}
                         </span>
                     </div>
                 </div>
                 <div class="flex items-center gap-3">
                     <Link :href="route('casos.show', caso.id)">
                         <SecondaryButton>Cancelar</SecondaryButton>
                     </Link>
                     <PrimaryButton @click="submit" :disabled="form.processing || isFormDisabled" :class="{ 'opacity-25': form.processing || isFormDisabled }">
                         Actualizar Caso
                     </PrimaryButton>
                     <DangerButton v-if="!isClosed" @click="openCloseModal">Cerrar Caso</DangerButton>
                     <PrimaryButton v-if="isClosed" @click="openReopenModal" class="!bg-blue-600 hover:!bg-blue-700 focus:!bg-blue-700 active:!bg-blue-800 focus:!ring-blue-500">
                         Reabrir Caso
                     </PrimaryButton>
                 </div>
             </div>
        </template>

        <div class="py-12">
            <!-- Max-w-5xl para un poco más de espacio -->
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-8">
                
                <div v-if="isClosed" class="bg-yellow-50 dark:bg-gray-800 border-l-4 border-yellow-400 p-4 rounded-r-lg">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.21 3.03-1.742 3.03H4.42c-1.532 0-2.492-1.696-1.742-3.03l5.58-9.92zM10 13a1 1 0 110-2 1 1 0 010 2zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-yellow-800 dark:text-yellow-200">Este caso está cerrado.</p>
                            <div v-if="caso.nota_cierre" class="mt-2 text-sm text-yellow-700 dark:text-yellow-300">
                                <p class="font-semibold">Nota de cierre:</p>
                                <p class="whitespace-pre-wrap">{{ caso.nota_cierre }}</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- --- INICIO: NAVEGACIÓN POR PESTAÑAS (MEJORADA) --- -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="border-b border-gray-200 dark:border-gray-700">
                        <nav class="-mb-px flex space-x-6 overflow-x-auto px-6" aria-label="Tabs">
                            <button @click="activeTab = 'info-principal'"
                                :class="[
                                    activeTab === 'info-principal'
                                        ? 'border-indigo-500 text-indigo-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:hover:text-gray-200 dark:hover:border-gray-500',
                                ]"
                                class="flex items-center whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200"
                            >
                                <InformationCircleIcon class="h-5 w-5 mr-2" /> Información Principal
                            </button>
                            <button @click="activeTab = 'proceso-judicial'"
                                :class="[
                                    activeTab === 'proceso-judicial'
                                        ? 'border-indigo-500 text-indigo-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:hover:text-gray-200 dark:hover:border-gray-500',
                                ]"
                                class="flex items-center whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200"
                            >
                                <ScaleIcon class="h-5 w-5 mr-2" /> Proceso Judicial
                            </button>
                            <button @click="activeTab = 'codeudores'"
                                :class="[
                                    activeTab === 'codeudores'
                                        ? 'border-indigo-500 text-indigo-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:hover:text-gray-200 dark:hover:border-gray-500',
                                ]"
                                class="flex items-center whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200"
                            >
                                <UsersIcon class="h-5 w-5 mr-2" /> Codeudores
                                <span v-if="form.codeudores.length > 0" class="ml-2 bg-indigo-100 text-indigo-600 text-xs font-bold px-2 py-0.5 rounded-full">{{ form.codeudores.length }}</span>
                            </button>
                            <button @click="activeTab = 'control-notas'"
                                :class="[
                                    activeTab === 'control-notas'
                                        ? 'border-indigo-500 text-indigo-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:hover:text-gray-200 dark:hover:border-gray-500',
                                ]"
                                class="flex items-center whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200"
                            >
                                <LockClosedIcon class="h-5 w-5 mr-2" /> Control y Notas
                            </button>
                        </nav>
                    </div>
                    <!-- --- FIN: NAVEGACIÓN POR PESTAÑAS --- -->

                    <div class="p-6 md:p-8 text-gray-900 dark:text-gray-100">
                        <form @submit.prevent="submit" class="space-y-8">
                            <fieldset :disabled="isFormDisabled" class="space-y-8">

                                <!-- --- INICIO: PESTAÑA 1 (PRINCIPAL) --- -->
                                <section v-show="activeTab === 'info-principal'" class="space-y-8">
                                    <section>
                                        <h3 class="text-lg font-medium border-b dark:border-gray-700 pb-2 mb-6">Partes Involucradas</h3>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <div>
                                                <InputLabel for="cooperativa" value="Cooperativa *" />
                                                <v-select id="cooperativa" v-model="selectedCooperativa" :options="cooperativasOptions" label="nombre" placeholder="Escribe para buscar..." @search="searchCooperativas" :filterable="false" :appendToBody="true" class="mt-1 block w-full vue-select-style">
                                                    <template #no-options>{{ cooperativasMessage }}</template>
                                                </v-select>
                                                <InputError :message="form.errors.cooperativa_id" class="mt-2" />
                                            </div>
                                            <div>
                                                <InputLabel for="abogado" value="Abogado/Gestor a Cargo *" />
                                                <v-select id="abogado" v-model="selectedAbogado" :options="abogadosOptions" label="name" placeholder="Escribe para buscar..." @search="searchAbogados" :filterable="false" :appendToBody="true" class="mt-1 block w-full vue-select-style">
                                                    <template #no-options>{{ abogadosMessage }}</template>
                                                </v-select>
                                                <InputError :message="form.errors.user_id" class="mt-2" />
                                            </div>
                                            <div class="md:col-span-2">
                                                <InputLabel for="deudor" value="Deudor Principal *" />
                                                <v-select id="deudor" v-model="selectedDeudor" :options="personasOptions" label="nombre_completo" placeholder="Escribe para buscar..." @search="searchPersonas" :filterable="false" :appendToBody="true" class="mt-1 block w-full vue-select-style">
                                                    <template #option="{ nombre_completo, numero_documento }">
                                                        <span>{{ nombre_completo }} ({{ numero_documento }})</span>
                                                    </template>
                                                    <template #no-options>{{ personasMessage }}</template>
                                                </v-select>
                                                <InputError :message="form.errors.deudor_id" class="mt-2" />
                                            </div>
                                        </div>
                                    </section>
                                    
                                    <section>
                                        <h3 class="text-lg font-medium border-b dark:border-gray-700 pb-2 mb-6">Información del Crédito</h3>
                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                            <div>
                                                <InputLabel for="referencia_credito" value="Numero De Pagare" />
                                                <TextInput v-model="form.referencia_credito" id="referencia_credito" type="text" class="mt-1 block w-full" />
                                                <InputError :message="form.errors.referencia_credito" class="mt-2" />
                                            </div>
                                            <div>
                                                <InputLabel for="monto_total" value="Monto Total *" />
                                                <TextInput v-model="form.monto_total" id="monto_total" type="number" step="0.01" class="mt-1 block w-full" />
                                                <InputError :message="form.errors.monto_total" class="mt-2" />
                                            </div>
                                            <div>
                                                <InputLabel for="tasa_interes_corriente" value="Tasa Interés Corriente (%)" />
                                                <TextInput v-model="form.tasa_interes_corriente" id="tasa_interes_corriente" type="number" step="0.01" class="mt-1 block w-full" />
                                                <InputError :message="form.errors.tasa_interes_corriente" class="mt-2" />
                                            </div>
                                            <div>
                                                <InputLabel for="fecha_tasa_interes" value="Fecha Tasa Interés" />
                                                <TextInput v-model="form.fecha_tasa_interes" id="fecha_tasa_interes" type="date" class="mt-1 block w-full" />
                                                <InputError :message="form.errors.fecha_tasa_interes" class="mt-2" />
                                            </div>
                                            <div>
                                                <InputLabel for="fecha_apertura" value="Fecha de Demanda" />
                                                <TextInput v-model="form.fecha_apertura" id="fecha_apertura" type="date" class="mt-1 block w-full" />
                                                <InputError :message="form.errors.fecha_apertura" class="mt-2" />
                                            </div>
                                            <div>
                                                <InputLabel for="fecha_vencimiento" value="Fecha de Vencimiento" />
                                                <TextInput v-model="form.fecha_vencimiento" id="fecha_vencimiento" type="date" class="mt-1 block w-full" />
                                                <InputError :message="form.errors.fecha_vencimiento" class="mt-2" />
                                            </div>
                                            <div>
                                                <InputLabel for="tipo_garantia_asociada" value="Tipo de Garantía" />
                                                <select v-model="form.tipo_garantia_asociada" id="tipo_garantia_asociada" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm">
                                                    <option :value="null">-- Sin especificar --</option>
                                                    <option value="codeudor">Codeudor</option>
                                                    <option value="hipotecaria">Hipotecaria</option>
                                                    <option value="prendaria">Prendaria</option>
                                                    <option value="sin garantía">Sin Garantía</option>
                                                    <option v-if="caso.tipo_garantia_asociada && !['codeudor', 'hipotecaria', 'prendaria', 'sin garantía', null].includes(caso.tipo_garantia_asociada)" :value="caso.tipo_garantia_asociada">
                                                        {{ caso.tipo_garantia_asociada }} (Antiguo)
                                                    </option>
                                                </select>
                                                <InputError :message="form.errors.tipo_garantia_asociada" class="mt-2" />
                                            </div>
                                            <div>
                                                <InputLabel for="origen_documental" value="Origen Documental" />
                                                <select v-model="form.origen_documental" id="origen_documental" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm">
                                                    <option :value="null">-- Sin especificar --</option>
                                                    <option value="pagaré">Pagaré</option>
                                                    <option value="libranza">Libranza</option>
                                                    <option value="contrato">Contrato</option>
                                                    <option value="otro">Otro</option>
                                                    <option v-if="caso.origen_documental && !['pagaré', 'libranza', 'contrato', 'otro', null].includes(caso.origen_documental)" :value="caso.origen_documental">
                                                        {{ caso.origen_documental }} (Valor Antiguo)
                                                    </option>
                                                </select>
                                                <InputError :message="form.errors.origen_documental" class="mt-2" />
                                            </div>
                                            <div class="lg:col-span-1">
                                                <InputLabel for="medio_contacto" value="Medio de Contacto" />
                                                <select v-model="form.medio_contacto" id="medio_contacto" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm">
                                                    <option :value="null">-- Sin especificar --</option>
                                                    <option value="email">Email</option>
                                                    <option value="telefono">Teléfono</option>
                                                    <option value="whatsapp">WhatsApp</option>
                                                    <option value="visita">Visita</option>
                                                    <option value="otro">Otro</option>
                                                </select>
                                                <InputError :message="form.errors.medio_contacto" class="mt-2" />
                                            </div>
                                        </div>
                                    </section>
                                </section>
                                <!-- --- FIN: PESTAÑA 1 --- -->

                                <!-- --- INICIO: PESTAÑA 2 (PROCESO) --- -->
                                <section v-show="activeTab === 'proceso-judicial'" class="space-y-8">
                                    <section>
                                        <h3 class="text-lg font-medium border-b dark:border-gray-700 pb-2 mb-6">Clasificación del Proceso</h3>
                                        <!-- CAMBIO L4: Rejilla de 4 columnas -->
                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                                            
                                            <!-- ===== INICIO: CORRECCIÓN "required" ===== -->
                                            <div>
                                                <InputLabel for="especialidad" value="Especialidad" /> <!-- " * " eliminado -->
                                                <select v-model="form.especialidad_id" id="especialidad" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"> <!-- "required" eliminado -->
                                                    <option :value="null">-- Opcional --</option> <!-- Texto cambiado -->
                                                    <option v-for="esp in especialidades" :key="esp.id" :value="esp.id">
                                                        {{ formatLabel(esp.nombre) }}
                                                    </option>
                                                </select>
                                                <InputError :message="form.errors.especialidad_id" class="mt-2" />
                                            </div>
                                            <div>
                                                <InputLabel for="tipo_proceso" value="Tipo de Proceso" /> <!-- " * " eliminado -->
                                                <select v-model="form.tipo_proceso" id="tipo_proceso" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" :disabled="!form.especialidad_id"> <!-- "required" eliminado -->
                                                    <option :value="null">-- Opcional --</option> <!-- Texto cambiado -->
                                                    <option v-for="tipo in tiposDisponibles" :key="tipo.id" :value="tipo.nombre">
                                                        {{ formatLabel(tipo.nombre) }}
                                                    </option>
                                                </select>
                                                <InputError :message="form.errors.tipo_proceso" class="mt-2" />
                                            </div>
                                            <div>
                                                <InputLabel for="subtipo_proceso" value="Proceso" /> <!-- " * " eliminado -->
                                                <select v-model="form.subtipo_proceso" id="subtipo_proceso" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" :disabled="!form.tipo_proceso"> <!-- "required" eliminado -->
                                                    <option :value="null">-- Opcional --</option> <!-- Texto cambiado -->
                                                    <option v-for="subtipo in subtiposDisponibles" :key="subtipo.id" :value="subtipo.nombre">
                                                        {{ formatLabel(subtipo.nombre) }}
                                                    </option>
                                                </select>
                                                <InputError class="mt-2" :message="form.errors.subtipo_proceso" />
                                            </div>
                                            <!-- ===== FIN: CORRECCIÓN "required" ===== -->

                                            <!-- --- INICIO: CAMBIO L4 (NUEVO DROPDOWN) --- -->
                                            <div>
                                                <InputLabel for="subproceso" value="Subproceso (Detalle)" />
                                                <select v-model="form.subproceso" id="subproceso" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" :disabled="!form.subtipo_proceso || subprocesosDisponibles.length === 0">
                                                    <option :value="null">-- Opcional --</option> <!-- Texto cambiado -->
                                                    <option v-for="subproceso in subprocesosDisponibles" :key="subproceso.id" :value="subproceso.nombre">
                                                        {{ formatLabel(subproceso.nombre) }} <!-- formatLabel AÑADIDO -->
                                                    </option>
                                                </select>
                                                <InputError class="mt-2" :message="form.errors.subproceso" />
                                            </div>
                                            <!-- --- FIN: CAMBIO L4 --- -->
                                        </div>
                                    </section>

                                    <section>
                                        <h3 class="text-lg font-medium border-b dark:border-gray-700 pb-2 mb-6">Información Judicial</h3>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <div>
                                                <InputLabel for="radicado" value="Radicado" />
                                                <TextInput v-model="form.radicado" id="radicado" type="text" class="mt-1 block w-full" />
                                                <InputError :message="form.errors.radicado" class="mt-2" />
                                            </div>
                                            <div>
                                                <InputLabel for="etapa_procesal" value="Etapa Procesal" />
                                                <select v-model="form.etapa_procesal" id="etapa_procesal" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm">
                                                    <option :value="null">-- Sin especificar --</option>
                                                    <option v-for="etapa in etapas_procesales" :key="etapa" :value="etapa">{{ formatLabel(etapa) }}</option>
                                                </select>
                                                <InputError class="mt-2" :message="form.errors.etapa_procesal" />
                                            </div>
                                            <div class="md:col-span-2">
                                                <InputLabel for="juzgado" value="Juzgado" />
                                                <v-select id="juzgado" v-model="selectedJuzgado" :options="juzgadosOptions" label="nombre" placeholder="Escribe para buscar un juzgado..." @search="searchJuzgados" :filterable="false" :appendToBody="true" class="mt-1 block w-full vue-select-style">
                                                    <template #no-options>{{ juzgadosMessage }}</template>
                                                </v-select>
                                                <InputError class="mt-2" :message="form.errors.juzgado_id" />
                                            </div>
                                        </div>
                                    </section>
                                </section>
                                <!-- --- FIN: PESTAÑA 2 --- -->

                                <!-- --- INICIO: PESTAÑA 3 (CODEUDORES "Anti-JSON") --- -->
                                <section v-show="activeTab === 'codeudores'" class="space-y-6">
                                    <div class="flex justify-between items-center border-b dark:border-gray-700 pb-2">
                                        <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100">Codeudores</h3>
                                        <PrimaryButton type="button" @click="addCodeudor" :disabled="isFormDisabled">
                                            <PlusIcon class="h-5 w-5 mr-2" /> Añadir Codeudor
                                        </PrimaryButton>
                                    </div>
                                    
                                    <div v-if="form.codeudores.length === 0" class="text-center text-gray-500 dark:text-gray-400 py-8 border-2 border-dashed dark:border-gray-700 rounded-lg">
                                        No se han añadido codeudores.
                                    </div>

                                    <div v-else class="space-y-6">
                                        <div v-for="(codeudor, codeudorIndex) in form.codeudores" :key="codeudorIndex" class="p-5 border dark:border-gray-700 rounded-lg shadow-sm bg-gray-50 dark:bg-gray-900/50 relative">
                                            
                                            <div class="flex justify-between items-center mb-4">
                                                <button type="button" @click="codeudor.showDetails = !codeudor.showDetails" class="flex-grow text-left p-1 -ml-1 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                                    <h4 class="text-md font-semibold text-indigo-600 dark:text-indigo-400 flex items-center">
                                                        <ChevronDownIcon v-if="codeudor.showDetails" class="h-5 w-5 mr-2 transition-transform" />
                                                        <ChevronUpIcon v-else class="h-5 w-5 mr-2 transition-transform" />
                                                        Codeudor {{ codeudorIndex + 1 }}: 
                                                        <span class="text-gray-900 dark:text-gray-100 font-medium ml-2">{{ codeudor.nombre_completo || '(Nuevo Codeudor)' }}</span>
                                                    </h4>
                                                </button>
                                                <!-- Botón minimalista en la esquina -->
                                                <DangerButton type="button" @click="removeCodeudor(codeudorIndex)" class="!p-2 absolute top-4 right-4">
                                                    <span class="sr-only">Eliminar Codeudor</span>
                                                    <TrashIcon class="h-5 w-5" />
                                                </DangerButton>
                                            </div>

                                            <div v-show="codeudor.showDetails" class="space-y-6">
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                                    <div>
                                                        <InputLabel :for="'codeudor_nombre_' + codeudorIndex" value="Nombre Completo *" />
                                                        <TextInput v-model="codeudor.nombre_completo" :id="'codeudor_nombre_' + codeudorIndex" type="text" class="mt-1 block w-full" required />
                                                        <InputError :message="form.errors['codeudores.' + codeudorIndex + '.nombre_completo']" class="mt-2" />
                                                    </div>
                                                    
                                                    <div class="grid grid-cols-2 gap-4">
                                                        <div>
                                                            <InputLabel :for="'codeudor_tipo_doc_' + codeudorIndex" value="Tipo Doc." />
                                                            <select v-model="codeudor.tipo_documento" :id="'codeudor_tipo_doc_' + codeudorIndex" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                                <option>CC</option>
                                                                <option>NIT</option>
                                                                <option>CE</option>
                                                                <option>PAS</option>
                                                            </select>
                                                        </div>
                                                        <div>
                                                            <InputLabel :for="'codeudor_num_doc_' + codeudorIndex" value="Nº Documento *" />
                                                            <TextInput v-model="codeudor.numero_documento" :id="'codeudor_num_doc_' + codeudorIndex" type="text" class="mt-1 block w-full" required />
                                                            <InputError :message="form.errors['codeudores.' + codeudorIndex + '.numero_documento']" class="mt-2" />
                                                        </div>
                                                    </div>

                                                    <div>
                                                        <InputLabel :for="'codeudor_celular_' + codeudorIndex" value="Celular" />
                                                        <TextInput v-model="codeudor.celular" :id="'codeudor_celular_' + codeudorIndex" type="text" class="mt-1 block w-full" />
                                                        <InputError :message="form.errors['codeudores.' + codeudorIndex + '.celular']" class="mt-2" />
                                                    </div>
                                                    
                                                    <div>
                                                        <InputLabel :for="'codeudor_correo_' + codeudorIndex" value="Correo Electrónico" />
                                                        <TextInput v-model="codeudor.correo" :id="'codeudor_correo_' + codeudorIndex" type="email" class="mt-1 block w-full" />
                                                        <InputError :message="form.errors['codeudores.' + codeudorIndex + '.correo']" class="mt-2" />
                                                    </div>
                                                </div>

                                                <!-- Sección Direcciones -->
                                                <div class="pt-4 border-t dark:border-gray-700">
                                                    <div class="flex justify-between items-center mb-3">
                                                        <h5 class="text-sm font-medium text-gray-700 dark:text-gray-300 flex items-center"><MapPinIcon class="h-4 w-4 mr-2 text-gray-400"/> Direcciones</h5>
                                                        <SecondaryButton type="button" @click="addAddress(codeudor)" class="!text-xs !py-1 !px-2">
                                                            <PlusIcon class="h-4 w-4 mr-1" />Añadir
                                                        </SecondaryButton>
                                                    </div>
                                                    <p v-if="!codeudor.addresses.length" class="text-xs text-center text-gray-500 dark:text-gray-400 py-2">Sin direcciones</p>
                                                    <div v-else class="space-y-3">
                                                        <div v-for="(addr, addrIndex) in codeudor.addresses" :key="addrIndex" class="grid grid-cols-1 md:grid-cols-8 gap-3 items-end">
                                                            <div class="md:col-span-2">
                                                                <InputLabel :for="`addr_label_${codeudorIndex}_${addrIndex}`" class="!text-xs">Etiqueta</InputLabel>
                                                                <TextInput v-model="addr.label" :id="`addr_label_${codeudorIndex}_${addrIndex}`" type="text" class="mt-1 block w-full" placeholder="Ej: Casa" />
                                                            </div>
                                                            <div class="md:col-span-3">
                                                                <InputLabel :for="`addr_addr_${codeudorIndex}_${addrIndex}`" class="!text-xs">Dirección</InputLabel>
                                                                <TextInput v-model="addr.address" :id="`addr_addr_${codeudorIndex}_${addrIndex}`" type="text" class="mt-1 block w-full" placeholder="Ej: Calle 10 # 42-10" />
                                                            </div>
                                                            <div class="md:col-span-2">
                                                                <InputLabel :for="`addr_city_${codeudorIndex}_${addrIndex}`" class="!text-xs">Ciudad</InputLabel>
                                                                <TextInput v-model="addr.city" :id="`addr_city_${codeudorIndex}_${addrIndex}`" type="text" class="mt-1 block w-full" placeholder="Ej: Medellín" />
                                                            </div>
                                                            <div class="md:col-span-1 text-right">
                                                                <DangerButton type="button" @click="removeAddress(codeudor, addrIndex)" class="!p-2">
                                                                    <span class="sr-only">Eliminar</span>
                                                                    <TrashIcon class="h-4 w-4" />
                                                                </DangerButton>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <InputError :message="form.errors['codeudores.' + codeudorIndex + '.addresses']" class="mt-2" />
                                                </div>

                                                <!-- Sección Redes Sociales -->
                                                <div class="pt-4 border-t dark:border-gray-700">
                                                    <div class="flex justify-between items-center mb-3">
                                                        <h5 class="text-sm font-medium text-gray-700 dark:text-gray-300 flex items-center"><LinkIcon class="h-4 w-4 mr-2 text-gray-400"/> Redes Sociales</h5>
                                                        <SecondaryButton type="button" @click="addSocial(codeudor)" class="!text-xs !py-1 !px-2">
                                                            <PlusIcon class="h-4 w-4 mr-1" />Añadir
                                                        </SecondaryButton>
                                                    </div>
                                                    <p v-if="!codeudor.social_links.length" class="text-xs text-center text-gray-500 dark:text-gray-400 py-2">Sin redes sociales</p>
                                                    <div v-else class="space-y-3">
                                                        <div v-for="(social, socialIndex) in codeudor.social_links" :key="socialIndex" class="grid grid-cols-1 md:grid-cols-8 gap-3 items-end">
                                                            <div class="md:col-span-3">
                                                                <InputLabel :for="`social_type_${codeudorIndex}_${socialIndex}`" class="!text-xs">Tipo</InputLabel>
                                                                <TextInput v-model="social.type" :id="`social_type_${codeudorIndex}_${socialIndex}`" type="text" class="mt-1 block w-full" placeholder="Ej: Facebook" />
                                                            </div>
                                                            <div class="md:col-span-4">
                                                                <InputLabel :for="`social_url_${codeudorIndex}_${socialIndex}`" class="!text-xs">URL o Usuario</InputLabel>
                                                                <TextInput v-model="social.url" :id="`social_url_${codeudorIndex}_${socialIndex}`" type="text" class="mt-1 block w-full" placeholder="Ej: http://fb.com/user" />
                                                            </div>
                                                            <div class="md:col-span-1 text-right">
                                                                <DangerButton type="button" @click="removeSocial(codeudor, socialIndex)" class="!p-2">
                                                                    <span class="sr-only">Eliminar</span>
                                                                    <TrashIcon class="h-4 w-4" />
                                                                </DangerButton>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <InputError :message="form.errors['codeudores.' + codeudorIndex + '.social_links']" class="mt-2" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </section>
                                <!-- --- FIN: PESTAÑA 3 --- -->

                                <!-- --- INICIO: PESTAÑA 4 (CONTROL) --- -->
                                <section v-show="activeTab === 'control-notas'" class="space-y-8">
                                    <section>
                                        <h3 class="text-lg font-medium border-b dark:border-gray-700 pb-2 mb-6">Estado y Control del Caso</h3>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <div>
                                                <InputLabel for="estado_proceso" value="Estado del Proceso *" />
                                                <!-- ===== INICIO: CORRECCIÓN DE PERMISOS ===== -->
                                                <!-- Deshabilitado si está cerrado Y NO eres admin -->
                                                <select v-model="form.estado_proceso" id="estado_proceso" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm" :disabled="isClosed && user.tipo_usuario !== 'admin'">
                                                <!-- ===== FIN: CORRECCIÓN DE PERMISOS ===== -->
                                                    <option value="prejurídico">Prejurídico</option>
                                                    <option value="demandado">Demandado</option>
                                                    <option value="en ejecución">En Ejecución</option>
                                                    <option value="sentencia">Sentencia</option>
                                                    <option value="cerrado">Cerrado</option>
                                                </select>
                                                <InputError :message="form.errors.estado_proceso" class="mt-2" />
                                            </div>
                                            <div>
                                                <InputLabel for="etapa_actual" value="Etapa Actual del Proceso" />
                                                <TextInput v-model="form.etapa_actual" id="etapa_actual" type="text" class="mt-1 block w-full" />
                                                <InputError :message="form.errors.etapa_actual" class="mt-2" />
                                            </div>
                                        </div>
                                    </section>
                                    
                                    <section>
                                        <h3 class="text-lg font-medium border-b dark:border-gray-700 pb-2 mb-6">Notas</h3>
                                        <div class="mt-4">
                                            <InputLabel for="notas_legales" value="Notas Legales / Internas" />
                                            <Textarea v-model="form.notas_legales" id="notas_legales" class="mt-1 block w-full" rows="4" />
                                            <InputError :message="form.errors.notas_legales" class="mt-2" />
                                        </div>
                                    </section>

                                    <section>
                                        <h3 class="text-lg font-medium border-b dark:border-gray-700 pb-2 mb-6">Control de Acceso</h3>
                                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 bg-gray-50 dark:bg-gray-900/40">
                                            <div class="flex items-start gap-3">
                                                <Checkbox id="bloqueado" v-model:checked="form.bloqueado" :disabled="user.tipo_usuario !== 'admin' || isClosed" />
                                                <div class="grow">
                                                    <InputLabel for="bloqueado" value="Caso bloqueado" />
                                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                                        Cuando está bloqueado solo un administrador puede modificar el caso.
                                                    </p>
                                                    <InputError :message="form.errors.bloqueado" class="mt-2" />
                                                </div>
                                            </div>
                                            <div v-if="form.bloqueado" class="mt-4">
                                                <InputLabel for="motivo_bloqueo" value="Motivo del bloqueo *" />
                                                <Textarea id="motivo_bloqueo" v-model.trim="form.motivo_bloqueo" class="mt-1 block w-full resize-y min-h-[96px]" rows="3" maxlength="1000" placeholder="Describe la razón del bloqueo…" :disabled="user.tipo_usuario !== 'admin' || isClosed"/>
                                                <InputError :message="form.errors.motivo_bloqueo" class="mt-2" />
                                            </div>
                                        </div>
                                    </section>
                                </section>
                                <!-- --- FIN: PESTAÑA 4 --- -->

                            </fieldset>

                            <div v-if="!isFormDisabled" class="flex items-center justify-end mt-8 border-t dark:border-gray-700 pt-6">
                                <Link :href="route('casos.show', caso.id)" class="text-sm text-gray-600 dark:text-gray-400 hover:underline mr-4">Cancelar</Link>
                                <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                    Actualizar Caso
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Modales -->
        <Modal :show="showCloseModal" @close="showCloseModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Cerrar Caso</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Al cerrar el caso, su estado cambiará a "Cerrado" y se deshabilitará la edición. Por favor, añade una nota de cierre.
                </p>
                <div class="mt-6">
                    <InputLabel for="nota_cierre" value="Nota de Cierre *" required />
                    <Textarea v-model="closeForm.nota_cierre" id="nota_cierre" class="mt-1 block w-full" rows="4" />
                    <InputError :message="closeForm.errors.nota_cierre" class="mt-2" />
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="showCloseModal = false">Cancelar</SecondaryButton>
                    <DangerButton @click="closeTheCase" :disabled="closeForm.processing" :class="{ 'opacity-25': closeForm.processing }">
                        {{ closeForm.processing ? 'Cerrando...' : 'Confirmar Cierre' }}
                    </DangerButton>
                </div>
            </div>
        </Modal>

        <Modal :show="showReopenModal" @close="showReopenModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Reabrir Caso</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    ¿Estás seguro de que quieres reabrir este caso? El estado volverá a "prejurídico" y la nota de cierre será eliminada permanentemente.
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="showReopenModal = false">Cancelar</SecondaryButton>
                    <PrimaryButton @click="reopenTheCase" :disabled="reopenForm.processing" :class="{ 'opacity-25': reopenForm.processing }" class="!bg-blue-600 hover:!bg-blue-700 focus:!bg-blue-700 active:!bg-blue-800 focus:!ring-blue-500">
                        {{ reopenForm.processing ? 'Reabriendo...' : 'Sí, Reabrir' }}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

    </AuthenticatedLayout>
</template>

<style>
/* Estilos para v-select */
.vue-select-style .vs__dropdown-toggle {
    border-color: #d1d5db; /* gray-300 */
    background-color: white;
    border-radius: 0.375rem; /* rounded-md */
    min-height: 42px;
}
.dark .vue-select-style .vs__dropdown-toggle {
    border-color: #4b5563; /* gray-600 */
    background-color: #1f2937; /* gray-800 */
}
.vue-select-style .vs__search, .vue-select-style .vs__selected {
    color: #111827; /* gray-900 */
    margin-top: 0;
    padding-left: 0.25rem;
}
.dark .vue-select-style .vs__search, .dark .vue-select-style .vs__selected {
    color: #f3f4f6; /* gray-100 */
}
.vue-select-style .vs__dropdown-menu {
    background-color: white;
    border-color: #d1d5db;
    z-index: 50;
}
.dark .vue-select-style .vs__dropdown-menu {
    background-color: #1f2937;
    border-color: #4b5563;
}
.vue-select-style .vs__option {
    color: #374151; /* gray-700 */
}
.dark .vue-select-style .vs__option {
    color: #d1d5db; /* gray-300 */
}
.vue-select-style .vs__option--highlight {
    background-color: #4f46e5; /* indigo-600 */
    color: white;
}
.vue-select-style .vs__clear, .vue-select-style .vs__open-indicator {
    fill: #6b7280; /* gray-500 */
}
.dark .vue-select-style .vs__clear, .dark .vue-select-style .vs__open-indicator {
    fill: #9ca3af; /* gray-400 */
}
.vue-select-style .vs__selected-options {
    padding-left: 0.25rem;
}
</style>

