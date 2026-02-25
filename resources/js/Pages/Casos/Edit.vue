<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Textarea from '@/Components/Textarea.vue';
import Checkbox from '@/Components/Checkbox.vue';
import Modal from '@/Components/Modal.vue';
import { Head, Link, useForm, usePage, useRemember, router } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted } from 'vue';
import vSelect from 'vue-select';
import 'vue-select/dist/vue-select.css';
import axios from 'axios';
import { 
    TrashIcon, InformationCircleIcon, ScaleIcon, UsersIcon, LockClosedIcon, 
    PlusIcon, ChevronUpIcon, ChevronDownIcon, ArchiveBoxXMarkIcon, ArrowPathIcon 
} from '@heroicons/vue/24/outline';

const props = defineProps({
    caso: { type: Object, required: true },
    estructuraProcesal: { type: Array, default: () => [] },
    etapas_procesales: { type: Array, default: () => [] },
});

const activeTab = useRemember('info-principal', 'casoEditTab:' + props.caso.id);
const user = usePage().props.auth.user;

// --- LÓGICA DE CIERRE/REAPERTURA ---
const showCloseModal = ref(false);
const closeForm = useForm({ nota_cierre: '' });

const confirmClose = () => {
    closeForm.nota_cierre = '';
    showCloseModal.value = true;
};

const submitClose = () => {
    closeForm.patch(route('casos.close', props.caso.id), {
        onSuccess: () => showCloseModal.value = false,
        preserveScroll: true,
    });
};

const submitReopen = () => {
    if (confirm('¿Estás seguro de querer reabrir este caso?')) {
        router.patch(route('casos.reopen', props.caso.id), {}, {
            preserveScroll: true,
        });
    }
};
// -----------------------------------

const isFormDisabled = computed(() => {
    const isAdmin = user.tipo_usuario === 'admin';
    if (isAdmin) return false;
    return props.caso.bloqueado;
});

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
            axios.get(route(routeName, { q: search })).then(response => {
                if (Array.isArray(response.data)) {
                    const mapped = response.data.map(mapResult).filter(Boolean);
                    options.value = mapped;
                    message.value = mapped.length === 0 ? 'No se encontraron resultados.' : '';
                } else { options.value = []; message.value = 'Error API'; }
            }).catch(() => { options.value = []; message.value = 'Error'; }).finally(() => loading(false));
        }, 500);
    };
    return { options, onSearch, message };
};

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

const { options: cooperativasOptions, onSearch: searchCooperativas, message: cooperativasMessage } = useDebouncedSearch('cooperativas.search', mapGeneric);
const { options: abogadosOptions, onSearch: searchAbogados, message: abogadosMessage } = useDebouncedSearch('users.search', mapUser);
const { options: juzgadosOptions, onSearch: searchJuzgados, message: juzgadosMessage } = useDebouncedSearch('juzgados.search', mapGeneric);
const { options: personasOptions, onSearch: searchPersonas, message: personasMessage } = useDebouncedSearch('personas.search', mapPersonas);

const formatDateForInput = (dateString) => {
    if (!dateString) return null;
    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return null;
        const year = date.getUTCFullYear();
        const month = (date.getUTCMonth() + 1).toString().padStart(2, '0');
        const day = date.getUTCDate().toString().padStart(2, '0');
        return `${year}-${month}-${day}`;
    } catch (e) { return null; }
};

const safeJsonParse = (jsonString) => {
    if (!jsonString) return [];
    if (typeof jsonString === 'object') return jsonString;
    try { const parsed = JSON.parse(jsonString); return Array.isArray(parsed) ? parsed : []; } catch (e) { return []; }
};

const form = useForm({
    _method: 'PATCH',
    cooperativa_id: props.caso.cooperativa_id,
    user_id: props.caso.user_id,
    deudor_id: props.caso.deudor_id,
    codeudores: props.caso.codeudores?.map(c => ({
        id: c.id,
        nombre_completo: c.nombre_completo || '',
        tipo_documento: c.tipo_documento || 'CC',
        numero_documento: c.numero_documento || '',
        celular: c.celular || '',
        correo: c.correo || '',
        addresses: safeJsonParse(c.addresses),
        social_links: safeJsonParse(c.social_links),
        showDetails: ref(true),
    })) || [],
    juzgado_id: props.caso.juzgado_id,
    referencia_credito: props.caso.referencia_credito,
    especialidad_id: props.caso.especialidad_id,
    tipo_proceso: props.caso.tipo_proceso,
    subtipo_proceso: props.caso.subtipo_proceso || null,
    subproceso: props.caso.subproceso || null,
    etapa_procesal: props.caso.etapa_procesal || null,
    radicado: props.caso.radicado ?? '',
    tipo_garantia_asociada: props.caso.tipo_garantia_asociada ?? null,
    origen_documental: props.caso.origen_documental ?? null,
    medio_contacto: props.caso.medio_contacto ?? null,
    fecha_apertura: formatDateForInput(props.caso.fecha_apertura),
    fecha_vencimiento: formatDateForInput(props.caso.fecha_vencimiento),
    fecha_inicio_credito: formatDateForInput(props.caso.fecha_inicio_credito),
    monto_total: props.caso.monto_total,
    monto_deuda_actual: props.caso.monto_deuda_actual,
    monto_total_pagado: props.caso.monto_total_pagado,
    tasa_interes_corriente: props.caso.tasa_interes_corriente,
    etapa_actual: props.caso.etapa_actual,
    bloqueado: !!props.caso.bloqueado,
    motivo_bloqueo: props.caso.motivo_bloqueo ?? '',
    notas_legales: props.caso.notas_legales,
    link_drive: props.caso.link_drive || '',
});

const nuevoCodeudorTemplate = () => ({ id: null, nombre_completo: '', tipo_documento: 'CC', numero_documento: '', celular: '', correo: '', addresses: [], social_links: [], showDetails: ref(true) });
const addCodeudor = () => { form.codeudores.push(nuevoCodeudorTemplate()); activeTab.value = 'codeudores'; };
const removeCodeudor = (index) => form.codeudores.splice(index, 1);

watch(() => form.bloqueado, v => { if (!v) form.motivo_bloqueo = ''; });

const formatLabel = (text) => {
    if (!text) return '';
    if (!text.includes('_') && (text.includes(' ') || text.includes('/'))) return text;
    return text.replace(/_/g, ' ').toLowerCase().replace(/\b\w/g, char => char.toUpperCase());
};

const especialidades = computed(() => props.estructuraProcesal);
const tiposDisponibles = ref([]);
const subtiposDisponibles = ref([]);
const subprocesosDisponibles = ref([]);

watch(() => form.especialidad_id, (newId) => {
    tiposDisponibles.value = []; 
    if (newId) {
        const esp = especialidades.value.find(e => e.id === newId);
        tiposDisponibles.value = esp ? esp.tipos_proceso : [];
        if (newId !== props.caso.especialidad_id) {
             form.tipo_proceso = null; 
             form.subtipo_proceso = null; 
             form.subproceso = null; 
        }
    }
}, { immediate: true });

watch(() => form.tipo_proceso, (newNombre) => {
    subtiposDisponibles.value = [];
    if (newNombre) {
        const tipo = tiposDisponibles.value.find(t => t.nombre === newNombre);
        subtiposDisponibles.value = tipo ? tipo.subtipos : [];
        if (newNombre !== props.caso.tipo_proceso) {
            form.subtipo_proceso = null; 
            form.subproceso = null;
        }
    }
}, { immediate: true });

watch(() => form.subtipo_proceso, (newNombre) => {
    subprocesosDisponibles.value = [];
    if (newNombre) {
        const subtipo = subtiposDisponibles.value.find(s => s.nombre === newNombre);
        subprocesosDisponibles.value = (subtipo && Array.isArray(subtipo.subprocesos)) ? subtipo.subprocesos : [];
        if (newNombre !== props.caso.subtipo_proceso) {
            form.subproceso = null;
        }
    }
}, { immediate: true });

onMounted(() => {
    if (props.caso.especialidad_id) {
        const esp = especialidades.value.find(e => e.id === props.caso.especialidad_id);
        if (esp) tiposDisponibles.value = esp.tipos_proceso;
    }
    if (props.caso.tipo_proceso) {
        const tipo = tiposDisponibles.value.find(t => t.nombre === props.caso.tipo_proceso);
        if (tipo) subtiposDisponibles.value = tipo.subtipos;
    }
    if (props.caso.subtipo_proceso) {
        const subtipo = subtiposDisponibles.value.find(s => s.nombre === props.caso.subtipo_proceso);
        if (subtipo) subprocesosDisponibles.value = subtipo.subprocesos;
    }
});

const selectedCooperativa = ref(null);
const selectedAbogado = ref(null);
const selectedDeudor = ref(null);
const selectedJuzgado = ref(null);

watch(selectedCooperativa, (newValue) => form.cooperativa_id = newValue?.id ?? null);
watch(selectedAbogado, (newValue) => form.user_id = newValue?.id ?? null);
watch(selectedDeudor, (newValue) => form.deudor_id = newValue?.id ?? null);
watch(selectedJuzgado, (newValue) => form.juzgado_id = newValue?.id ?? null);

const initSelectData = () => {
    if (props.caso.cooperativa) { selectedCooperativa.value = {id: props.caso.cooperativa.id, nombre: props.caso.cooperativa.nombre}; cooperativasOptions.value = [selectedCooperativa.value]; }
    if (props.caso.user) { selectedAbogado.value = {id: props.caso.user.id, name: props.caso.user.name}; abogadosOptions.value = [selectedAbogado.value]; }
    if (props.caso.juzgado) { selectedJuzgado.value = {id: props.caso.juzgado.id, nombre: props.caso.juzgado.nombre}; juzgadosOptions.value = [selectedJuzgado.value]; }
    const initialPersonas = [];
    if (props.caso.deudor) { selectedDeudor.value = mapPersonas(props.caso.deudor); initialPersonas.push(selectedDeudor.value); }
    personasOptions.value = [...new Map(initialPersonas.filter(Boolean).map(item => [item.id, item])).values()];
};

initSelectData();

const submit = () => {
    form.transform(data => ({
        ...data,
        codeudores: data.codeudores.map(c => ({
            ...c,
            addresses: c.addresses || [],
            social_links: c.social_links || []
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
                         <span v-if="caso.nota_cierre" class="px-2 py-0.5 text-xs rounded bg-gray-100 text-gray-700 font-medium border border-gray-300">Cerrado</span>
                     </div>
                 </div>
                 <div class="flex items-center gap-3">
                     <Link :href="route('casos.show', caso.id)">
                         <SecondaryButton>Cancelar</SecondaryButton>
                     </Link>
                     <PrimaryButton @click="submit" :disabled="form.processing || isFormDisabled" :class="{ 'opacity-25': form.processing || isFormDisabled }">
                         Actualizar Caso
                     </PrimaryButton>
                 </div>
             </div>
        </template>

        <div class="py-12">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-8">
                
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="border-b border-gray-200 dark:border-gray-700">
                        <nav class="-mb-px flex space-x-6 overflow-x-auto px-6" aria-label="Tabs">
                            <button @click="activeTab = 'info-principal'" :class="[activeTab === 'info-principal' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:hover:text-gray-200 dark:hover:border-gray-500']" class="flex items-center whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200">
                                <InformationCircleIcon class="h-5 w-5 mr-2" /> Información Principal
                            </button>
                            <button @click="activeTab = 'proceso-judicial'" :class="[activeTab === 'proceso-judicial' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:hover:text-gray-200 dark:hover:border-gray-500']" class="flex items-center whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200">
                                <ScaleIcon class="h-5 w-5 mr-2" /> Proceso Judicial
                            </button>
                            <button @click="activeTab = 'codeudores'" :class="[activeTab === 'codeudores' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:hover:text-gray-200 dark:hover:border-gray-500']" class="flex items-center whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200">
                                <UsersIcon class="h-5 w-5 mr-2" /> Codeudores <span v-if="form.codeudores.length > 0" class="ml-2 bg-indigo-100 text-indigo-600 text-xs font-bold px-2 py-0.5 rounded-full">{{ form.codeudores.length }}</span>
                            </button>
                            <button @click="activeTab = 'control-notas'" :class="[activeTab === 'control-notas' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:hover:text-gray-200 dark:hover:border-gray-500']" class="flex items-center whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200">
                                <LockClosedIcon class="h-5 w-5 mr-2" /> Control y Notas
                            </button>
                        </nav>
                    </div>

                    <div class="p-6 md:p-8 text-gray-900 dark:text-gray-100">
                        <form @submit.prevent="submit" class="space-y-8">
                            <fieldset :disabled="isFormDisabled" class="space-y-8">

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
                                                    <template #option="{ nombre_completo, numero_documento }"><span>{{ nombre_completo }} ({{ numero_documento }})</span></template>
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
                                                <InputLabel for="monto_total" value="Monto de Crédito (Inicial) *" />
                                                <TextInput v-model="form.monto_total" id="monto_total" type="number" step="0.01" class="mt-1 block w-full" />
                                                <InputError :message="form.errors.monto_total" class="mt-2" />
                                            </div>
                                            <div>
                                                <InputLabel for="monto_deuda_actual" value="Monto Deuda Actual" />
                                                <TextInput v-model="form.monto_deuda_actual" id="monto_deuda_actual" type="number" step="0.01" class="mt-1 block w-full" placeholder="Dejar en 0 si es igual al monto del crédito" />
                                                <InputError :message="form.errors.monto_deuda_actual" class="mt-2" />
                                            </div>
                                            <div>
                                                <InputLabel for="monto_total_pagado" value="Monto Total Pagado" />
                                                <TextInput v-model="form.monto_total_pagado" id="monto_total_pagado" type="number" step="0.01" class="mt-1 block w-full" placeholder="Pagos realizados antes de registrar" />
                                                <InputError :message="form.errors.monto_total_pagado" class="mt-2" />
                                            </div>
                                            <div>
                                                <InputLabel for="fecha_inicio_credito" value="Fecha Inicio del Crédito" />
                                                <TextInput v-model="form.fecha_inicio_credito" id="fecha_inicio_credito" type="date" class="mt-1 block w-full" />
                                                <InputError :message="form.errors.fecha_inicio_credito" class="mt-2" />
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

                                            <div class="md:col-span-2 lg:col-span-3">
                                                <InputLabel for="link_drive" value="URL Carpeta Drive (Opcional)" />
                                                <div class="relative mt-1 rounded-md shadow-sm">
                                                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                                        <span class="text-gray-500 sm:text-sm">https://</span>
                                                    </div>
                                                    <TextInput v-model="form.link_drive" id="link_drive" type="url" class="block w-full pl-16" placeholder="drive.google.com/..." />
                                                </div>
                                                <InputError :message="form.errors.link_drive" class="mt-2" />
                                            </div>

                                            <div>
                                                <InputLabel for="tipo_garantia_asociada" value="Tipo de Garantía" />
                                                <select v-model="form.tipo_garantia_asociada" id="tipo_garantia_asociada" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm">
                                                    <option :value="null">-- Sin especificar --</option>
                                                    <option value="codeudor">Codeudor</option>
                                                    <option value="hipotecaria">Hipotecaria</option>
                                                    <option value="prendaria">Prendaria</option>
                                                    <option value="sin garantía">Sin Garantía</option>
                                                </select>
                                                <InputError :message="form.errors.tipo_garantia_asociada" class="mt-2" />
                                            </div>
                                            <div class="md:col-span-1">
                                                <InputLabel for="origen_documental" value="Origen Documental" />
                                                <select v-model="form.origen_documental" id="origen_documental" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm">
                                                    <option :value="null">-- Sin especificar --</option>
                                                    <option value="pagaré">Pagaré</option>
                                                    <option value="libranza">Libranza</option>
                                                    <option value="contrato">Contrato</option>
                                                    <option value="otro">Otro</option>
                                                </select>
                                                <InputError :message="form.errors.origen_documental" class="mt-2" />
                                            </div>
                                            <div class="lg:col-span-1">
                                                <InputLabel for="medio_contacto" value="Medio de Contacto" />
                                                <select v-model="form.medio_contacto" id="medio_contacto" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm">
                                                    <option :value="null">-- Sin especificar --</option>
                                                    <option value="email">Email</option><option value="telefono">Teléfono</option><option value="whatsapp">WhatsApp</option><option value="visita">Visita</option><option value="otro">Otro</option>
                                                </select>
                                                <InputError :message="form.errors.medio_contacto" class="mt-2" />
                                            </div>
                                        </div>
                                    </section>
                                </section>

                                <section v-show="activeTab === 'proceso-judicial'" class="space-y-8">
                                    <section>
                                        <h3 class="text-lg font-medium border-b dark:border-gray-700 pb-2 mb-6">Clasificación del Proceso</h3>
                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                                            <div>
                                                <InputLabel for="especialidad" value="Especialidad" />
                                                <select v-model="form.especialidad_id" id="especialidad" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                    <option :value="null">-- Opcional --</option>
                                                    <option v-for="esp in especialidades" :key="esp.id" :value="esp.id">
                                                        {{ formatLabel(esp.nombre) }}
                                                    </option>
                                                </select>
                                                <InputError :message="form.errors.especialidad_id" class="mt-2" />
                                            </div>
                                            <div>
                                                <InputLabel for="tipo_proceso" value="Tipo de Proceso" />
                                                <select v-model="form.tipo_proceso" id="tipo_proceso" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" :disabled="!form.especialidad_id">
                                                    <option :value="null">-- Opcional --</option>
                                                    <option v-for="tipo in tiposDisponibles" :key="tipo.id" :value="tipo.nombre">
                                                        {{ formatLabel(tipo.nombre) }}
                                                    </option>
                                                </select>
                                                <InputError :message="form.errors.tipo_proceso" class="mt-2" />
                                            </div>
                                            <div>
                                                <InputLabel for="subtipo_proceso" value="Proceso" />
                                                <select v-model="form.subtipo_proceso" id="subtipo_proceso" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" :disabled="!form.tipo_proceso">
                                                    <option :value="null">-- Opcional --</option>
                                                    <option v-for="subtipo in subtiposDisponibles" :key="subtipo.id" :value="subtipo.nombre">{{ formatLabel(subtipo.nombre) }}</option>
                                                </select>
                                                <InputError class="mt-2" :message="form.errors.subtipo_proceso" />
                                            </div>
                                            <div>
                                                <InputLabel for="subproceso" value="Subproceso (Detalle)" />
                                                <select v-model="form.subproceso" id="subproceso" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" :disabled="!form.subtipo_proceso || subprocesosDisponibles.length === 0">
                                                    <option :value="null">-- Opcional --</option>
                                                    <option v-for="subproceso in subprocesosDisponibles" :key="subproceso.id" :value="subproceso.nombre">
                                                        {{ formatLabel(subproceso.nombre) }}
                                                    </option>
                                                </select>
                                                <InputError class="mt-2" :message="form.errors.subproceso" />
                                            </div>
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
                                                <DangerButton type="button" @click="removeCodeudor(codeudorIndex)" class="!p-2 absolute top-4 right-4">
                                                    <span class="sr-only">Eliminar Codeudor</span><TrashIcon class="h-5 w-5" />
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
                                                                <option>CC</option><option>NIT</option><option>CE</option><option>PAS</option>
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
                                                    </div>
                                                    <div>
                                                        <InputLabel :for="'codeudor_correo_' + codeudorIndex" value="Correo Electrónico" />
                                                        <TextInput v-model="codeudor.correo" :id="'codeudor_correo_' + codeudorIndex" type="email" class="mt-1 block w-full" />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </section>

                                <section v-show="activeTab === 'control-notas'" class="space-y-8">
                                    <section>
                                        <h3 class="text-lg font-medium border-b dark:border-gray-700 pb-2 mb-6">Estado y Control del Caso</h3>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <div>
                                                <InputLabel for="etapa_actual" value="Etapa Actual del Proceso" />
                                                <TextInput v-model="form.etapa_actual" id="etapa_actual" type="text" class="mt-1 block w-full" />
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
                                                <Checkbox id="bloqueado" v-model:checked="form.bloqueado" :disabled="user.tipo_usuario !== 'admin'" />
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
                                                <Textarea id="motivo_bloqueo" v-model.trim="form.motivo_bloqueo" class="mt-1 block w-full resize-y min-h-[96px]" rows="3" maxlength="1000" placeholder="Describe la razón del bloqueo…" :disabled="user.tipo_usuario !== 'admin'"/>
                                                <InputError :message="form.errors.motivo_bloqueo" class="mt-2" />
                                            </div>
                                        </div>
                                    </section>

                                    <!-- NUEVA SECCIÓN: Acciones de Cierre -->
                                    <section v-if="user.tipo_usuario !== 'cliente'">
                                        <h3 class="text-lg font-medium border-b dark:border-gray-700 pb-2 mb-6 text-red-600 dark:text-red-400 flex items-center">
                                            <ArchiveBoxXMarkIcon class="h-5 w-5 mr-2" />
                                            Cierre del Caso
                                        </h3>
                                        <div class="rounded-lg border border-red-200 dark:border-red-900/50 p-4 bg-red-50 dark:bg-red-900/20">
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <h4 class="font-medium text-red-800 dark:text-red-300">
                                                        {{ caso.nota_cierre ? 'Reabrir Caso' : 'Cerrar Caso' }}
                                                    </h4>
                                                    <p class="text-sm text-red-600 dark:text-red-400 mt-1">
                                                        {{ caso.nota_cierre 
                                                            ? 'El caso se encuentra cerrado. Puedes reabrirlo para continuar la gestión.' 
                                                            : 'Finalizar la gestión de este caso. Se requerirá una nota de cierre.' 
                                                        }}
                                                    </p>
                                                </div>
                                                <div>
                                                    <PrimaryButton v-if="caso.nota_cierre" type="button" @click="submitReopen" class="bg-indigo-600 hover:bg-indigo-500">
                                                        <ArrowPathIcon class="h-4 w-4 mr-2" /> Reabrir
                                                    </PrimaryButton>
                                                    <DangerButton v-else type="button" @click="confirmClose">
                                                        <ArchiveBoxXMarkIcon class="h-4 w-4 mr-2" /> Cerrar
                                                    </DangerButton>
                                                </div>
                                            </div>
                                            <div v-if="caso.nota_cierre" class="mt-4 p-3 bg-white dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700">
                                                <p class="text-xs font-bold text-gray-500 uppercase mb-1">Motivo de Cierre:</p>
                                                <p class="text-sm text-gray-700 dark:text-gray-300">{{ caso.nota_cierre }}</p>
                                            </div>
                                        </div>
                                    </section>
                                </section>

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

        <!-- MODAL DE CIERRE DE CASO -->
        <Modal :show="showCloseModal" @close="showCloseModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Confirmar Cierre del Caso
                </h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Por favor, ingresa una nota final explicando el motivo del cierre. Esta acción finalizará la gestión del caso.
                </p>
                <div class="mt-6">
                    <InputLabel for="nota_cierre" value="Nota de Cierre" />
                    <Textarea
                        id="nota_cierre"
                        v-model="closeForm.nota_cierre"
                        class="mt-1 block w-full"
                        rows="4"
                        placeholder="Ej: Acuerdo de pago total cumplido..."
                    />
                    <InputError :message="closeForm.errors.nota_cierre" class="mt-2" />
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="showCloseModal = false"> Cancelar </SecondaryButton>
                    <DangerButton @click="submitClose" :class="{ 'opacity-25': closeForm.processing }" :disabled="closeForm.processing">
                        Cerrar Caso Permanentemente
                    </DangerButton>
                </div>
            </div>
        </Modal>

    </AuthenticatedLayout>
</template>

<style>
.vue-select-style .vs__dropdown-toggle {
    border-color: #d1d5db;
    background-color: white;
    border-radius: 0.375rem;
    min-height: 42px;
}
.dark .vue-select-style .vs__dropdown-toggle {
    border-color: #4b5563;
    background-color: #1f2937;
}
.vue-select-style .vs__search, .vue-select-style .vs__selected {
    color: #111827;
    margin-top: 0;
    padding-left: 0.25rem;
}
.dark .vue-select-style .vs__search, .dark .vue-select-style .vs__selected {
    color: #f3f4f6;
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
    color: #374151;
}
.dark .vue-select-style .vs__option {
    color: #d1d5db;
}
.vue-select-style .vs__option--highlight {
    background-color: #4f46e5;
    color: white;
}
.vue-select-style .vs__clear, .vue-select-style .vs__open-indicator {
    fill: #6b7280;
}
.dark .vue-select-style .vs__clear, .dark .vue-select-style .vs__open-indicator {
    fill: #9ca3af;
}
.vue-select-style .vs__selected-options {
    padding-left: 0.25rem;
}
</style>