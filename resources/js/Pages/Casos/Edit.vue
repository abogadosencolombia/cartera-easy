<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import TextInput from '@/Components/TextInput.vue';
import DatePicker from '@/Components/DatePicker.vue';
import Textarea from '@/Components/Textarea.vue';
import Checkbox from '@/Components/Checkbox.vue';
import Modal from '@/Components/Modal.vue';
import Dropdown from '@/Components/Dropdown.vue';
import SelectInput from '@/Components/SelectInput.vue';
import AsyncSelect from '@/Components/AsyncSelect.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import { Head, Link, useForm, usePage, useRemember, router } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted } from 'vue';
import axios from 'axios';
import { debounce } from 'lodash';
import { formatRadicado, addDaysToDate, addMonthsToDate, toUpperCase, calculateDV } from '@/Utils/formatters';
import { getCaseFinancialStatus } from '@/Utils/caseFinancialStatus';
import { 
    TrashIcon, InformationCircleIcon, ScaleIcon, UsersIcon, LockClosedIcon, 
    PlusIcon, ChevronUpIcon, ChevronDownIcon, ArchiveBoxXMarkIcon, ArrowPathIcon,
    BriefcaseIcon, BuildingOfficeIcon, CheckCircleIcon, ClockIcon, DocumentTextIcon,
    UserCircleIcon, CalendarDaysIcon, MapPinIcon, PhoneIcon,
    EnvelopeIcon, LinkIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    caso: { type: Object, required: true },
    estructuraProcesal: { type: Array, default: () => [] },
    etapas_procesales: { type: Array, default: () => [] },
});

const validTabs = ['info-principal', 'proceso-judicial', 'codeudores', 'control-notas'];
const getInitialTab = () => {
    if (typeof window === 'undefined') return 'info-principal';
    const params = new URLSearchParams(window.location.search);
    const tabParam = params.get('tab');
    return (tabParam && validTabs.includes(tabParam)) ? tabParam : 'info-principal';
};

const activeTab = useRemember(getInitialTab(), `EditCasoTab:${props.caso.id}`);
const user = usePage().props.auth.user;

// --- SINCRONIZACIÓN CON URL ---
const setActiveTab = (tab) => {
    activeTab.value = tab;
    const url = new URL(window.location);
    url.searchParams.set('tab', tab);
    window.history.replaceState({}, '', url);
};

// --- LÓGICA DE CIERRE/REAPERTURA ---
const showCloseModal = ref(false);
const closeForm = useForm({ nota_cierre: '' });
const confirmClose = () => { closeForm.nota_cierre = ''; showCloseModal.value = true; };
const submitClose = () => {
    closeForm.patch(route('casos.close', props.caso.id), { onSuccess: () => showCloseModal.value = false, preserveScroll: true });
};
const submitReopen = () => {
    if (confirm('¿Reabrir este caso?')) router.patch(route('casos.reopen', props.caso.id), {}, { preserveScroll: true });
};

const isFormDisabled = computed(() => (user.tipo_usuario !== 'admin' && props.caso.bloqueado));

const isMinimized = ref(false);

const formatDateForInput = (d) => d ? new Date(d).toISOString().split('T')[0] : null;
const safeJsonParse = (s) => { if (!s) return []; try { const p = JSON.parse(s); return Array.isArray(p) ? p : []; } catch (e) { return []; } };

const form = useForm({
    _method: 'PATCH',
    cooperativa_id: props.caso.cooperativa ? { id: props.caso.cooperativa.id, nombre: props.caso.cooperativa.nombre } : null,
    user_id: props.caso.users?.length > 0 
        ? props.caso.users.map(u => ({ id: u.id, name: u.name })) 
        : (props.caso.user ? [{ id: props.caso.user.id, name: props.caso.user.name }] : []),
    deudor_id: props.caso.deudor_id,
    deudor: {
        id: props.caso.deudor_id,
        selected: props.caso.deudor ? { id: props.caso.deudor.id, nombre_completo: props.caso.deudor.nombre_completo, numero_documento: props.caso.deudor.numero_documento } : null,
        is_new: false,
        nombre_completo: '', tipo_documento: 'CC', numero_documento: '', dv: '', celular_1: '', correo_1: '', cooperativas_ids: [], abogados_ids: []
    },
    codeudores: props.caso.codeudores?.map(c => ({
        id: c.id, nombre_completo: c.nombre_completo || '', tipo_documento: c.tipo_documento || 'CC', numero_documento: c.numero_documento || '',
        dv: c.dv || '',
        celular: c.celular || '', correo: c.correo || '', addresses: safeJsonParse(c.addresses), social_links: safeJsonParse(c.social_links), showDetails: true
    })) || [],
    juzgado_id: props.caso.juzgado ? { id: props.caso.juzgado.id, nombre: props.caso.juzgado.nombre } : null,
    referencia_credito: props.caso.referencia_credito,
    especialidad_id: props.caso.especialidad_id,
    tipo_proceso: props.caso.tipo_proceso,
    subtipo_proceso: props.caso.subtipo_proceso,
    subproceso: props.caso.subproceso,
    etapa_procesal: props.caso.etapa_procesal,
    radicado: props.caso.radicado ?? '',
    tipo_garantia_asociada: props.caso.tipo_garantia_asociada,
    origen_documental: props.caso.origen_documental,
    medio_contacto: props.caso.medio_contacto,
    fecha_apertura: formatDateForInput(props.caso.fecha_apertura),
    fecha_vencimiento: formatDateForInput(props.caso.fecha_vencimiento),
    fecha_inicio_credito: formatDateForInput(props.caso.fecha_inicio_credito),
    monto_total: props.caso.monto_total,
    monto_deuda_actual: props.caso.monto_deuda_actual,
    monto_total_pagado: props.caso.monto_total_pagado,
    bloqueado: !!props.caso.bloqueado,
    motivo_bloqueo: props.caso.motivo_bloqueo ?? '',
    notas_legales: props.caso.notas_legales,
    link_drive: props.caso.link_drive || '',
    link_expediente: props.caso.link_expediente || '',
    sin_codeudores: !!props.caso.sin_codeudores,
    es_spoa_nunc: !!props.caso.es_spoa_nunc,
});

const financialFormStatus = computed(() => getCaseFinancialStatus(form));

const caseSummaryCards = computed(() => [
    {
        label: 'Empresa',
        value: form.cooperativa_id?.nombre || 'Sin asignar',
        detail: 'Cooperativa / entidad',
        icon: BuildingOfficeIcon,
        class: 'border-indigo-200 bg-indigo-50 text-indigo-700 dark:border-indigo-900/60 dark:bg-indigo-950/30 dark:text-indigo-300',
    },
    {
        label: 'Deudor',
        value: form.deudor.is_new ? (form.deudor.nombre_completo || 'Nuevo deudor') : (form.deudor.selected?.nombre_completo || 'Sin deudor'),
        detail: form.deudor.is_new ? 'Registro nuevo' : 'Persona existente',
        icon: UserCircleIcon,
        class: 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900/60 dark:bg-sky-950/30 dark:text-sky-300',
    },
    {
        label: 'Codeudores',
        value: form.sin_codeudores ? 'Sin codeudores' : form.codeudores.length,
        detail: form.sin_codeudores ? 'Estado confirmado' : 'Garantias personales',
        icon: UsersIcon,
        class: form.sin_codeudores
            ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-300'
            : 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-300',
    },
    {
        label: 'Estado',
        value: props.caso.nota_cierre ? 'Cerrado' : 'Activo',
        detail: isFormDisabled.value ? 'Bloqueado para edicion' : 'Editable',
        icon: LockClosedIcon,
        class: props.caso.nota_cierre || isFormDisabled.value
            ? 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/60 dark:bg-rose-950/30 dark:text-rose-300'
            : 'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-200',
    },
]);

const tabItems = computed(() => [
    { id: 'info-principal', label: 'Info principal', icon: InformationCircleIcon, badge: financialFormStatus.value.hasMissing ? 'Revisar' : null },
    { id: 'proceso-judicial', label: 'Proceso judicial', icon: ScaleIcon, badge: form.radicado ? null : 'Sin radicado' },
    { id: 'codeudores', label: 'Codeudores', icon: UsersIcon, badge: form.sin_codeudores ? 'Sin' : form.codeudores.length },
    { id: 'control-notas', label: 'Control y notas', icon: LockClosedIcon, badge: props.caso.nota_cierre ? 'Cerrado' : null },
]);

watch(() => props.caso.id, (newId) => {
    if (newId) {
        form.defaults({
            cooperativa_id: props.caso.cooperativa ? { id: props.caso.cooperativa.id, nombre: props.caso.cooperativa.nombre } : null,
            user_id: props.caso.users?.length > 0 
                ? props.caso.users.map(u => ({ id: u.id, name: u.name })) 
                : (props.caso.user ? [{ id: props.caso.user.id, name: props.caso.user.name }] : []),
            deudor_id: props.caso.deudor_id,
            deudor: {
                id: props.caso.deudor_id,
                selected: props.caso.deudor ? { id: props.caso.deudor.id, nombre_completo: props.caso.deudor.nombre_completo, numero_documento: props.caso.deudor.numero_documento } : null,
                is_new: false,
                nombre_completo: '', tipo_documento: 'CC', numero_documento: '', dv: '', celular_1: '', correo_1: '', cooperativas_ids: [], abogados_ids: []
            },
            codeudores: props.caso.codeudores?.map(c => ({
                id: c.id, nombre_completo: c.nombre_completo || '', tipo_documento: c.tipo_documento || 'CC', numero_documento: c.numero_documento || '',
                dv: c.dv || '',
                celular: c.celular || '', correo: c.correo || '', addresses: safeJsonParse(c.addresses), social_links: safeJsonParse(c.social_links), showDetails: true
            })) || [],
            juzgado_id: props.caso.juzgado ? { id: props.caso.juzgado.id, nombre: props.caso.juzgado.nombre } : null,
            referencia_credito: props.caso.referencia_credito,
            especialidad_id: props.caso.especialidad_id,
            tipo_proceso: props.caso.tipo_proceso,
            subtipo_proceso: props.caso.subtipo_proceso,
            subproceso: props.caso.subproceso,
            etapa_procesal: props.caso.etapa_procesal,
            radicado: props.caso.radicado ?? '',
            tipo_garantia_asociada: props.caso.tipo_garantia_asociada,
            origen_documental: props.caso.origen_documental,
            medio_contacto: props.caso.medio_contacto,
            fecha_apertura: formatDateForInput(props.caso.fecha_apertura),
            fecha_vencimiento: formatDateForInput(props.caso.fecha_vencimiento),
            fecha_inicio_credito: formatDateForInput(props.caso.fecha_inicio_credito),
            monto_total: props.caso.monto_total,
            monto_deuda_actual: props.caso.monto_deuda_actual,
            monto_total_pagado: props.caso.monto_total_pagado,
            bloqueado: !!props.caso.bloqueado,
            motivo_bloqueo: props.caso.motivo_bloqueo ?? '',
            notas_legales: props.caso.notas_legales,
            link_drive: props.caso.link_drive || '',
            link_expediente: props.caso.link_expediente || '',
            sin_codeudores: !!props.caso.sin_codeudores,
            es_spoa_nunc: !!props.caso.es_spoa_nunc,
        });
        form.reset();
    }
}, { immediate: true });

// --- DUPLICADOS Y ALERTAS ---
const casosExistentes = ref([]);
const buscandoCasos = ref(false);
const faltaIdentificacion = computed(() => !form.radicado && !form.referencia_credito);

const checkDuplicados = debounce(() => {
    if (!form.radicado && !form.referencia_credito) {
        casosExistentes.value = [];
        return;
    }
    
    buscandoCasos.value = true;
    axios.get(route('casos.verificar_duplicados'), {
        params: {
            radicado: form.radicado,
            referencia_credito: form.referencia_credito,
            ignore_id: props.caso.id
        }
    })
    .then(res => {
        casosExistentes.value = res.data;
    })
    .catch(err => console.error('Error buscando duplicados:', err))
    .finally(() => buscandoCasos.value = false);
}, 500);

watch(() => form.radicado, checkDuplicados);
watch(() => form.referencia_credito, checkDuplicados);

// --- AUTO-FORMATO ---
watch(() => form.deudor.numero_documento, (newVal) => {
    if (form.deudor.is_new && form.deudor.tipo_documento === 'NIT' && newVal) {
        form.deudor.dv = calculateDV(newVal).toString();
    }
});

const addCodeudor = () => { 
    form.sin_codeudores = false;
    form.codeudores.push({ id: null, nombre_completo: '', tipo_documento: 'CC', numero_documento: '', celular: '', correo: '', addresses: [], social_links: [], showDetails: true }); 
    activeTab.value = 'codeudores'; 
};
const removeCodeudor = (idx) => form.codeudores.splice(idx, 1);
const addAddress = (idx) => form.codeudores[idx].addresses.push({ label: 'Casa', address: '', city: '' });
const removeAddress = (idx, addrIdx) => form.codeudores[idx].addresses.splice(addrIdx, 1);

const handleRadicadoInput = (e) => {
    const formatted = formatRadicado(e.target.value);
    form.radicado = formatted;
    e.target.value = formatted;
};

const addDays = (field, days) => {
    form[field] = addDaysToDate(form[field], days);
};

const addMonths = (field, months) => {
    form[field] = addMonthsToDate(form[field], months);
};

// --- CASCADA ---
const formatLabel = (text) => text?.replace(/_/g, ' ').toLowerCase().replace(/\b\w/g, c => c.toUpperCase()) || '';
const especialidades = computed(() => props.estructuraProcesal);
const tiposDisponibles = ref([]);
const subtiposDisponibles = ref([]);
const subprocesosDisponibles = ref([]);

// Opciones rápidas con iconos (Diseño Premium)
const opcionesRapidasEsp = computed(() => {
    const iconos = {
        'CIVIL': ScaleIcon,
        'LABORAL': BriefcaseIcon,
        'FAMILIA': UsersIcon,
        'COMERCIAL': BuildingOfficeIcon
    };
    return especialidades.value
        .filter(e => iconos[e.nombre])
        .map(e => ({ ...e, icono: iconos[e.nombre] }))
        .sort((a, b) => a.nombre.localeCompare(b.nombre));
});

const etapasRapidas = [
    { id: 'DEMANDA PRESENTADA', icono: DocumentTextIcon },
    { id: 'MANDAMIENTO DE PAGO', icono: CheckCircleIcon },
    { id: 'AUDIENCIA INICIAL (ART. 372 CGP)', icono: ClockIcon },
    { id: 'TERMINADO POR PAGO TOTAL', icono: ArrowPathIcon }
];

watch(() => form.especialidad_id, (id, oldId) => {
    const esp = especialidades.value.find(e => e.id === id);
    tiposDisponibles.value = esp ? esp.tipos_proceso : [];
    if (oldId !== undefined) {
        form.tipo_proceso = null;
        form.subtipo_proceso = null;
        form.subproceso = null;
    }
}, { immediate: true });

watch(() => form.tipo_proceso, (val, oldVal) => {
    const t = tiposDisponibles.value.find(x => x.nombre === val);
    subtiposDisponibles.value = t ? t.subtipos : [];
    if (oldVal !== undefined) {
        form.subtipo_proceso = null;
        form.subproceso = null;
    }
}, { immediate: true });

watch(() => form.subtipo_proceso, (val, oldVal) => {
    const s = subtiposDisponibles.value.find(x => x.nombre === val);
    subprocesosDisponibles.value = s ? s.subprocesos : [];
    if (oldVal !== undefined) {
        form.subproceso = null;
    }
}, { immediate: true });

const submit = () => {
    form.transform(data => ({
        ...data,
        cooperativa_id: data.cooperativa_id?.id ?? null,
        user_id: Array.isArray(data.user_id) ? data.user_id.map(u => u.id) : [],
        juzgado_id: data.juzgado_id?.id ?? null,
        deudor: data.deudor.is_new ? { 
            ...data.deudor, 
            is_new: true,
            cooperativas_ids: data.deudor.cooperativas_ids.map(c => c.id), 
            abogados_ids: data.deudor.abogados_ids.map(a => a.id) 
        } : { is_new: false, id: data.deudor.selected?.id },
        deudor_id: data.deudor.is_new ? null : data.deudor.selected?.id,
    })).patch(route('casos.update', props.caso.id));
};
</script>

<template>
    <Head :title="'Editar Caso #' + caso.id" />
    <AuthenticatedLayout>
        <template #header>
             <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                 <div class="min-w-0">
                     <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Edicion de expediente</p>
                     <div class="mt-1 flex flex-wrap items-center gap-2">
                        <h2 class="truncate text-2xl font-black tracking-tight text-gray-950 dark:text-white">
                            Caso #{{ caso.id }}
                        </h2>
                        <span class="rounded-md border px-2.5 py-1 text-[10px] font-black uppercase" :class="caso.nota_cierre ? 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/60 dark:bg-rose-950/30 dark:text-rose-300' : 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-300'">
                            {{ caso.nota_cierre ? 'Cerrado' : 'Activo' }}
                        </span>
                        <span v-if="isFormDisabled" class="rounded-md border border-amber-200 bg-amber-50 px-2.5 py-1 text-[10px] font-black uppercase text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-300">
                            Bloqueado
                        </span>
                     </div>
                     <p class="mt-1 max-w-4xl text-sm font-medium text-gray-600 dark:text-gray-300">
                        Actualiza datos financieros, proceso judicial, codeudores, ubicaciones y notas internas.
                     </p>
                 </div>
                 <div class="flex w-full flex-col-reverse gap-2 sm:w-auto sm:flex-row sm:items-center sm:justify-end">
                     <Link :href="route('casos.show', caso.id)" class="w-full sm:w-auto">
                        <SecondaryButton class="w-full justify-center sm:w-auto">Cancelar</SecondaryButton>
                     </Link>
                     <PrimaryButton class="w-full justify-center sm:w-auto" @click="submit" :disabled="form.processing || isFormDisabled">
                        <ArrowPathIcon v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                        Actualizar caso
                     </PrimaryButton>
                 </div>
             </div>
        </template>

        <div class="case-edit-page min-h-screen bg-gray-50/60 py-6 dark:bg-gray-900/40">
            <div class="mx-auto max-w-[1600px] space-y-5 px-4 sm:px-6 lg:px-8">
                <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <article
                        v-for="item in caseSummaryCards"
                        :key="item.label"
                        class="rounded-lg border p-4 shadow-sm"
                        :class="item.class"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-[10px] font-black uppercase tracking-widest opacity-80">{{ item.label }}</p>
                                <p class="mt-1 truncate text-sm font-black" :title="String(item.value)">{{ item.value }}</p>
                                <p class="mt-1 truncate text-xs font-semibold opacity-90" :title="item.detail">{{ item.detail }}</p>
                            </div>
                            <component :is="item.icon" class="h-5 w-5 shrink-0 opacity-80" />
                        </div>
                    </article>
                </section>

                <div class="overflow-visible rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="border-b border-gray-200 px-3 dark:border-gray-700">
                        <nav class="flex gap-2 overflow-x-auto py-3" aria-label="Secciones de edicion del caso">
                            <button
                                v-for="tab in tabItems"
                                :key="tab.id"
                                @click="setActiveTab(tab.id)"
                                :class="activeTab === tab.id
                                    ? 'bg-indigo-600 text-white shadow-sm'
                                    : 'text-gray-600 hover:bg-gray-50 hover:text-gray-950 dark:text-gray-300 dark:hover:bg-gray-700/70 dark:hover:text-white'"
                                class="inline-flex shrink-0 items-center rounded-lg px-3 py-2 text-[11px] font-black uppercase tracking-wider transition"
                            >
                                <component :is="tab.icon" class="mr-2 h-4 w-4" :class="activeTab === tab.id ? 'text-white' : 'text-gray-400'" />
                                {{ tab.label }}
                                <span
                                    v-if="tab.badge !== null && tab.badge !== undefined && tab.badge !== ''"
                                    class="ml-2 rounded-full px-2 py-0.5 text-[9px] font-black"
                                    :class="activeTab === tab.id ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-200'"
                                >
                                    {{ tab.badge }}
                                </span>
                            </button>
                        </nav>
                    </div>

                    <!-- Alertas de Duplicados o Identificación Incompleta -->
                    <div class="space-y-4 px-4 pt-5 sm:px-6 lg:px-8">
                        <!-- Alerta Crítica: Sin Codeudores -->
                        <div v-if="form.codeudores.length === 0 && !form.sin_codeudores && !isMinimized" 
                            class="p-5 bg-rose-50 dark:bg-rose-900/20 border-2 border-rose-200 dark:border-rose-800 rounded-3xl flex flex-col md:flex-row items-center justify-between gap-6 animate-in slide-in-from-top-4">
                            <div class="flex items-center gap-4 text-center md:text-left">
                                <div class="p-3 bg-rose-100 dark:bg-rose-900/50 rounded-2xl shadow-sm shrink-0">
                                    <UsersIcon class="w-8 h-8 text-rose-600" />
                                </div>
                                <div>
                                    <h3 class="text-sm font-black text-rose-800 dark:text-rose-300 uppercase tracking-widest">¡Atención! Proceso sin codeudores</h3>
                                    <p class="text-xs font-bold text-rose-600 dark:text-rose-400 mt-1">Este expediente no registra garantías de terceros. ¿Desea agregarlos ahora o confirmar que no tiene?</p>
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center justify-center gap-3 shrink-0">
                                <button type="button" @click="setActiveTab('codeudores')" class="px-5 py-2.5 bg-rose-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-rose-700 transition-all shadow-lg shadow-rose-200 dark:shadow-none">
                                    Agregar Ahora
                                </button>
                                <button type="button" @click="form.sin_codeudores = true" class="px-5 py-2.5 bg-white dark:bg-gray-800 border border-rose-200 dark:border-gray-700 text-rose-600 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-rose-50 transition-all">
                                    Marcar sin codeudores
                                </button>
                                <button type="button" @click="isMinimized = true" class="px-4 py-2 text-[10px] font-bold text-rose-400 uppercase hover:text-rose-600 transition-colors">
                                    Más tarde
                                </button>
                            </div>
                        </div>

                        <!-- Estado: Confirmado sin codeudores -->
                        <div v-if="form.sin_codeudores" class="p-4 bg-emerald-50 dark:bg-emerald-900/10 border border-emerald-100 dark:border-emerald-800/50 rounded-2xl flex items-center justify-between gap-4 animate-in zoom-in-95">
                            <div class="flex items-center gap-3">
                                <div class="p-2 bg-emerald-100 dark:bg-emerald-900/30 rounded-lg">
                                    <CheckCircleIcon class="w-5 h-5 text-emerald-600" />
                                </div>
                                <div>
                                    <h4 class="text-[10px] font-black text-emerald-800 dark:text-emerald-300 uppercase tracking-widest">Estado verificado: Sin Codeudores</h4>
                                    <p class="text-[9px] text-emerald-600 dark:text-emerald-500 font-bold uppercase tracking-tighter">Este proceso se maneja sin garantías adicionales.</p>
                                </div>
                            </div>
                            <button type="button" @click="form.sin_codeudores = false" class="text-[9px] font-black text-emerald-600 uppercase hover:underline">Cambiar Estado</button>
                        </div>

                        <div v-if="faltaIdentificacion" class="p-4 bg-blue-50 dark:bg-blue-900/10 border border-blue-100 dark:border-blue-800/50 rounded-2xl flex items-center gap-3 animate-pulse">
                            <InformationCircleIcon class="w-5 h-5 text-blue-500" />
                            <p class="text-[10px] font-black text-blue-700 dark:text-blue-400 uppercase tracking-widest">
                                El proceso no tiene Pagaré ni Radicado asignado actualmente.
                            </p>
                        </div>
                        
                        <div v-if="casosExistentes.length > 0" class="p-6 bg-amber-50 dark:bg-amber-900/10 border border-amber-200/50 dark:border-amber-800/50 rounded-3xl animate-in zoom-in-95 mt-4">
                            <div class="flex items-center gap-3 mb-4">
                                <div class="p-2 bg-amber-100 dark:bg-amber-900/30 rounded-lg">
                                    <ClockIcon class="w-5 h-5 text-amber-600" />
                                </div>
                                <div>
                                    <h4 class="text-xs font-black text-amber-800 dark:text-amber-300 uppercase tracking-widest">Alerta de Duplicados</h4>
                                    <p class="text-[10px] text-amber-600 dark:text-amber-500 font-bold uppercase tracking-tighter">Se detectaron {{ casosExistentes.length }} coincidencias por Radicado o Pagaré</p>
                                </div>
                            </div>
                            <div class="space-y-3 max-h-64 overflow-y-auto pr-2 custom-scrollbar">
                                <div v-for="(item, idx) in casosExistentes" :key="idx" class="flex items-center justify-between bg-white/80 dark:bg-gray-800/80 p-4 rounded-2xl border border-amber-100/50 dark:border-amber-900/50 shadow-sm group hover:border-amber-400 transition-colors">
                                    <div class="flex flex-col">
                                        <div class="flex items-center gap-2 mb-1">
                                            <span class="text-[10px] font-black text-indigo-600 bg-indigo-50 dark:bg-indigo-900/30 px-2 py-0.5 rounded-md uppercase tracking-tighter">{{ item.tipo }}</span>
                                            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">ID #{{ item.id }}</span>
                                            <span v-if="item.radicado === form.radicado && form.radicado" class="text-[9px] font-black text-red-600 bg-red-50 px-1.5 rounded uppercase">Mismo Radicado</span>
                                            <span v-if="item.referencia_credito === form.referencia_credito && form.referencia_credito" class="text-[9px] font-black text-red-600 bg-red-50 px-1.5 rounded uppercase">Mismo Pagaré</span>
                                        </div>
                                        <span class="text-sm font-black text-gray-700 dark:text-gray-200">{{ item.radicado || 'SIN RADICADO' }}</span>
                                        <div class="flex flex-col gap-0.5 mt-1">
                                            <span class="flex items-center gap-1 text-[9px] text-gray-500 font-bold uppercase"><UserCircleIcon class="w-3 h-3" /> {{ item.deudor }}</span>
                                            <div class="flex items-center gap-3">
                                                <span class="flex items-center gap-1 text-[9px] text-gray-500 font-bold uppercase"><BuildingOfficeIcon class="w-3 h-3" /> {{ item.cooperativa }}</span>
                                                <span class="flex items-center gap-1 text-[9px] text-gray-500 font-bold uppercase"><CalendarDaysIcon class="w-3 h-3" /> {{ item.fecha }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <a :href="item.link" target="_blank" class="p-3 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 rounded-xl opacity-0 group-hover:opacity-100 transition-all hover:bg-indigo-600 hover:text-white shadow-lg shadow-indigo-200 dark:shadow-none">
                                        <DocumentTextIcon class="w-5 h-5" />
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="p-4 sm:p-6 lg:p-8">
                        <fieldset :disabled="isFormDisabled">
                            <!-- TAB 1 -->
                            <section v-show="activeTab === 'info-principal'" class="space-y-8">
                                <div class="rounded-2xl border border-gray-200 bg-gray-50/60 p-4 dark:border-gray-700 dark:bg-gray-800/35 sm:p-6">
                                <div class="grid grid-cols-1 gap-5 md:grid-cols-2 lg:gap-6">
                                    <div><InputLabel value="Cooperativa / Empresa *" /><AsyncSelect v-model="form.cooperativa_id" :endpoint="route('cooperativas.search')" placeholder="Asignar a..." label-key="nombre" /><InputError :message="form.errors.cooperativa_id" /></div>
                                    <div><InputLabel value="Abogado(s) a Cargo *" /><AsyncSelect v-model="form.user_id" :endpoint="route('users.search')" multiple placeholder="Asignar gestores..." label-key="name" /><InputError :message="form.errors.user_id" /></div>
                                    <div class="md:col-span-2 space-y-4">
                                        <div class="flex justify-between items-center"><InputLabel value="Deudor Principal *" /><button type="button" @click="form.deudor.is_new = !form.deudor.is_new" class="text-[10px] font-bold text-indigo-600 uppercase">{{ form.deudor.is_new ? '← Buscar' : '+ Nuevo' }}</button></div>
                                        <div v-if="!form.deudor.is_new">
                                            <AsyncSelect v-model="form.deudor.selected" :endpoint="route('personas.search')" label-key="nombre_completo" />
                                            <InputError :message="form.errors.deudor_id" />
                                            <InputError :message="form.errors['deudor.id']" />
                                        </div>
                                        <div v-else class="grid grid-cols-1 gap-4 rounded-2xl border border-indigo-100 bg-white p-4 shadow-sm dark:border-indigo-900/60 dark:bg-gray-900 md:grid-cols-3">
                                            <div>
                                                <TextInput v-model="form.deudor.nombre_completo" @blur="form.deudor.nombre_completo = toUpperCase(form.deudor.nombre_completo)" placeholder="Nombre completo" class="w-full" />
                                                <InputError :message="form.errors['deudor.nombre_completo']" />
                                            </div>
                                            <div>
                                                <SelectInput v-model="form.deudor.tipo_documento"><option>CC</option><option>NIT</option><option>CE</option></SelectInput>
                                                <InputError :message="form.errors['deudor.tipo_documento']" />
                                            </div>
                                            <div>
                                                <div :class="form.deudor.tipo_documento === 'NIT' ? 'grid grid-cols-4 gap-2' : ''">
                                                    <div :class="form.deudor.tipo_documento === 'NIT' ? 'col-span-3' : ''">
                                                        <TextInput v-model="form.deudor.numero_documento" placeholder="Documento" class="w-full" />
                                                    </div>
                                                    <div v-if="form.deudor.tipo_documento === 'NIT'">
                                                        <TextInput v-model="form.deudor.dv" maxlength="1" placeholder="DV" class="w-full text-center px-0" />
                                                    </div>
                                                </div>
                                                <InputError :message="form.errors['deudor.numero_documento']" />
                                                <InputError :message="form.errors['deudor.dv']" />
                                            </div>
                                            <div>
                                                <TextInput v-model="form.deudor.celular_1" placeholder="Celular" class="w-full" />
                                                <InputError :message="form.errors['deudor.celular_1']" />
                                            </div>
                                            <div class="md:col-span-2">
                                                <TextInput v-model="form.deudor.correo_1" type="email" placeholder="Correo Electrónico" class="w-full" />
                                                <InputError :message="form.errors['deudor.correo_1']" />
                                            </div>
                                            <div class="md:col-span-3 grid grid-cols-1 gap-4 mt-2 md:grid-cols-2">
                                                <div>
                                                    <AsyncSelect v-model="form.deudor.cooperativas_ids" :endpoint="route('cooperativas.search')" multiple label-key="nombre" placeholder="Cooperativas * (Mín. 1)" />
                                                    <InputError :message="form.errors['deudor.cooperativas_ids']" class="mt-2" />
                                                </div>
                                                <AsyncSelect v-model="form.deudor.abogados_ids" :endpoint="route('users.search')" multiple label-key="name" placeholder="Abogados..." />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                </div>
                                <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900 sm:p-6 space-y-4">
                                    <div>
                                        <h3 class="text-base font-black text-gray-950 dark:text-gray-100">Información financiera y fechas</h3>
                                        <p class="mt-1 text-xs font-semibold text-gray-500 dark:text-gray-400">Montos, pagaré y fechas operativas del crédito.</p>
                                    </div>
                                    <div
                                        v-if="financialFormStatus.hasMissing"
                                        class="p-4 rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/15 flex items-start gap-3"
                                    >
                                        <InformationCircleIcon class="h-5 w-5 text-amber-600 dark:text-amber-300 shrink-0 mt-0.5" />
                                        <div>
                                            <p class="text-xs font-black text-amber-900 dark:text-amber-100 uppercase">Montos financieros por completar</p>
                                            <p class="text-[11px] font-semibold text-amber-700 dark:text-amber-300 mt-1">{{ financialFormStatus.detail }} Puede continuar, pero el expediente quedará marcado para revisión.</p>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
                                        <div><InputLabel value="Número De Pagaré" /><TextInput v-model="form.referencia_credito" class="w-full" /><InputError :message="form.errors.referencia_credito" /></div>
                                        <div><InputLabel value="Monto de Crédito *" /><TextInput v-model="form.monto_total" type="number" step="0.01" class="w-full" /><InputError :message="form.errors.monto_total" /></div>
                                        <div><InputLabel value="Monto Deuda Actual" /><TextInput v-model="form.monto_deuda_actual" type="number" step="0.01" class="w-full" /><InputError :message="form.errors.monto_deuda_actual" /></div>
                                        <div><InputLabel value="Total Pagado" /><TextInput v-model="form.monto_total_pagado" type="number" step="0.01" class="w-full" /><InputError :message="form.errors.monto_total_pagado" /></div>
                                        <div>
                                            <InputLabel value="Fecha de Demanda *" />
                                            <DatePicker v-model="form.fecha_apertura" class="w-full" />
                                            <div class="mt-1 flex gap-1">
                                                <button type="button" @click="addDays('fecha_apertura', 3)" class="text-[9px] bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded font-bold hover:bg-indigo-100 text-gray-600">+3d</button>
                                                <button type="button" @click="addDays('fecha_apertura', 5)" class="text-[9px] bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded font-bold hover:bg-indigo-100 text-gray-600">+5d</button>
                                                <button type="button" @click="addMonths('fecha_apertura', 1)" class="text-[9px] bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded font-bold hover:bg-indigo-100 text-gray-600">+1m</button>
                                            </div>
                                            <InputError :message="form.errors.fecha_apertura" />
                                        </div>
                                        <div>
                                            <InputLabel value="Fecha Vencimiento" />
                                            <DatePicker v-model="form.fecha_vencimiento" class="w-full" />
                                            <div class="mt-1 flex gap-1">
                                                <button type="button" @click="addMonths('fecha_vencimiento', 6)" class="text-[9px] bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded font-bold hover:bg-indigo-100 text-gray-600">+6m</button>
                                                <button type="button" @click="addMonths('fecha_vencimiento', 12)" class="text-[9px] bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded font-bold hover:bg-indigo-100 text-gray-600">+1a</button>
                                            </div>
                                            <InputError :message="form.errors.fecha_vencimiento" />
                                        </div>
                                        <div><InputLabel value="Fecha Inicio Crédito" /><DatePicker v-model="form.fecha_inicio_credito" class="w-full" /><InputError :message="form.errors.fecha_inicio_credito" /></div>
                                    </div>
                                </div>
                            </section>

                            <!-- TAB 2 -->
                            <section v-show="activeTab === 'proceso-judicial'" class="space-y-8">
                                <div class="rounded-2xl border border-gray-200 bg-gray-50/60 p-4 dark:border-gray-700 dark:bg-gray-800/35 sm:p-6">
                                <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
                                    <div>
                                        <div class="flex justify-between items-center mb-1">
                                            <InputLabel value="Radicado" />
                                            <label class="flex items-center gap-1.5 cursor-pointer group">
                                                <input type="checkbox" v-model="form.es_spoa_nunc" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 w-3 h-3" />
                                                <span class="text-[9px] font-black uppercase text-gray-400 group-hover:text-indigo-600 transition-colors">¿Es SPOA/NUNC?</span>
                                            </label>
                                        </div>
                                        <TextInput 
                                            v-model="form.radicado" 
                                            @input="handleRadicadoInput"
                                            class="w-full font-mono" 
                                            :placeholder="form.es_spoa_nunc ? '21 dígitos (Sistema Penal)' : '23 dígitos numéricos (Sin puntos ni guiones)'"
                                        />
                                        <InputError :message="form.errors.radicado" />
                                    </div>
                                    <div><InputLabel value="Juzgado" /><AsyncSelect v-model="form.juzgado_id" :endpoint="route('juzgados.search')" label-key="nombre" /><InputError :message="form.errors.juzgado_id" /></div>
                                    <div>
                                        <InputLabel value="Especialidad *" />
                                        <SearchableSelect
                                            v-model="form.especialidad_id"
                                            :options="especialidades"
                                            :featured-options="[8, 12, 10, 11]"
                                            value-key="id"
                                            label-key="nombre"
                                            :format-label="formatLabel"
                                            placeholder="Seleccione especialidad..."
                                        />
                                        <InputError :message="form.errors.especialidad_id" />
                                    </div>
                                    <div>
                                        <InputLabel value="Tipo de Proceso *" />
                                        <SearchableSelect
                                            v-model="form.tipo_proceso"
                                            :options="tiposDisponibles"
                                            value-key="nombre"
                                            label-key="nombre"
                                            :format-label="formatLabel"
                                            :disabled="!form.especialidad_id"
                                            placeholder="Seleccione tipo..."
                                        />
                                        <InputError :message="form.errors.tipo_proceso" />
                                    </div>
                                    <div>
                                        <InputLabel value="Proceso *" />
                                        <SearchableSelect
                                            v-model="form.subtipo_proceso"
                                            :options="subtiposDisponibles"
                                            value-key="nombre"
                                            label-key="nombre"
                                            :format-label="formatLabel"
                                            :disabled="!form.tipo_proceso"
                                            placeholder="Seleccione proceso..."
                                        />
                                        <InputError :message="form.errors.subtipo_proceso" />
                                    </div>
                                    <div>
                                        <InputLabel value="Subproceso (Detalle)" />
                                        <SearchableSelect
                                            v-model="form.subproceso"
                                            :options="subprocesosDisponibles"
                                            value-key="nombre"
                                            label-key="nombre"
                                            :disabled="!form.subtipo_proceso"
                                            placeholder="Seleccione subproceso..."
                                        />
                                        <InputError :message="form.errors.subproceso" />
                                    </div>
                                    <div>
                                        <InputLabel value="Etapa Procesal" />
                                        <SearchableSelect
                                            v-model="form.etapa_procesal"
                                            :options="etapas_procesales.map(e => ({ id: e, nombre: e }))"
                                            :featured-options="[1220, 1233, 1281]"
                                            value-key="id"
                                            label-key="nombre"
                                            :format-label="formatLabel"
                                            placeholder="Seleccione etapa..."
                                        />
                                        <InputError :message="form.errors.etapa_procesal" />
                                    </div>
                                    <div><InputLabel value="Tipo de Garantía *" /><SelectInput v-model="form.tipo_garantia_asociada"><option value="codeudor">Codeudor</option><option value="hipotecaria">Hipotecaria</option><option value="prendaria">Prendaria</option><option value="sin garantía">Sin garantía</option></SelectInput><InputError :message="form.errors.tipo_garantia_asociada" /></div>
                                    <div><InputLabel value="Origen Documental *" /><SelectInput v-model="form.origen_documental"><option value="pagaré">Pagaré</option><option value="libranza">Libranza</option><option value="contrato">Contrato</option><option value="otro">Otro</option></SelectInput><InputError :message="form.errors.origen_documental" /></div>
                                    <div><InputLabel value="Medio de Contacto" /><SelectInput v-model="form.medio_contacto"><option :value="null">-- Seleccione --</option><option value="email">Email</option><option value="whatsapp">WhatsApp</option><option value="telefono">Teléfono</option></SelectInput><InputError :message="form.errors.medio_contacto" /></div>
                                    <div class="xl:col-span-3 grid grid-cols-1 gap-5 md:grid-cols-2">
                                        <div><InputLabel value="URL Carpeta Drive (Opcional)" /><TextInput v-model="form.link_drive" type="url" class="w-full" placeholder="https://drive.google.com/..." /><InputError :message="form.errors.link_drive" /></div>
                                        <div><InputLabel value="URL Expediente Digital (Opcional)" /><TextInput v-model="form.link_expediente" type="url" class="w-full" placeholder="https://expediente.justicia.gov.co/..." /><InputError :message="form.errors.link_expediente" /></div>
                                    </div>
                                </div>
                                </div>
                            </section>

                            <!-- TAB 3 -->
                            <section v-show="activeTab === 'codeudores'" class="space-y-5">
                                <div class="flex flex-col gap-3 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <p class="text-[10px] font-bold uppercase tracking-widest text-indigo-600 dark:text-indigo-300">Garantias personales</p>
                                        <h3 class="text-base font-black text-gray-950 dark:text-white">Codeudores vinculados</h3>
                                        <p class="mt-1 text-xs font-semibold text-gray-500 dark:text-gray-400">Edita identificacion, contacto, ubicaciones y enlaces digitales de cada codeudor.</p>
                                    </div>
                                    <div class="flex flex-col gap-2 sm:flex-row">
                                        <button
                                            v-if="form.codeudores.length === 0 && !form.sin_codeudores"
                                            type="button"
                                            @click="form.sin_codeudores = true"
                                            class="inline-flex items-center justify-center rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-[10px] font-black uppercase tracking-widest text-amber-700 transition hover:bg-amber-100 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-300"
                                        >
                                            Marcar sin codeudores
                                        </button>
                                        <PrimaryButton type="button" @click="addCodeudor" class="justify-center">
                                            <PlusIcon class="mr-2 h-4 w-4" /> Añadir codeudor
                                        </PrimaryButton>
                                    </div>
                                </div>

                                <div v-if="form.codeudores.length === 0 && !form.sin_codeudores" class="rounded-lg border border-dashed border-gray-300 bg-white p-10 text-center dark:border-gray-700 dark:bg-gray-900">
                                    <UsersIcon class="mx-auto mb-3 h-10 w-10 text-gray-300" />
                                    <p class="text-sm font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">No se han vinculado codeudores</p>
                                    <p class="mt-1 text-xs font-medium text-gray-500 dark:text-gray-400">Agrega un deudor solidario o confirma que el titulo no tiene garantias personales.</p>
                                </div>

                                <div v-for="(c, i) in form.codeudores" :key="i" class="relative rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                                    <div class="mb-5 flex flex-col gap-3 border-b border-gray-200 pb-4 pr-12 dark:border-gray-700 sm:flex-row sm:items-start sm:justify-between">
                                        <div>
                                            <p class="text-[10px] font-black uppercase tracking-widest text-indigo-600 dark:text-indigo-300">Codeudor #{{ i + 1 }}</p>
                                            <h4 class="mt-1 text-base font-black text-gray-950 dark:text-white">{{ c.nombre_completo || 'Sin nombre registrado' }}</h4>
                                            <p class="mt-1 text-xs font-semibold text-gray-500 dark:text-gray-400">{{ c.tipo_documento || 'CC' }} {{ c.numero_documento || 'Sin documento' }}</p>
                                        </div>
                                        <button type="button" @click="removeCodeudor(i)" class="absolute right-4 top-4 rounded-lg border border-rose-200 bg-rose-50 p-2 text-rose-600 transition hover:bg-rose-600 hover:text-white dark:border-rose-900/60 dark:bg-rose-950/30 dark:text-rose-300" title="Eliminar codeudor">
                                            <TrashIcon class="h-4 w-4"/>
                                        </button>
                                    </div>

                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                        <div class="md:col-span-1">
                                            <InputLabel value="Nombre completo *" />
                                            <TextInput v-model="c.nombre_completo" @blur="c.nombre_completo = toUpperCase(c.nombre_completo)" class="mt-1 block w-full" required />
                                            <InputError :message="form.errors[`codeudores.${i}.nombre_completo`]" />
                                        </div>
                                        <div>
                                            <InputLabel value="Tipo documento" />
                                            <SelectInput v-model="c.tipo_documento">
                                                <option value="CC">Cedula de Ciudadania</option>
                                                <option value="NIT">NIT</option>
                                                <option value="CE">Cedula de Extranjeria</option>
                                            </SelectInput>
                                        </div>
                                        <div>
                                            <InputLabel value="Numero documento *" />
                                            <div :class="c.tipo_documento === 'NIT' ? 'grid grid-cols-4 gap-2' : ''">
                                                <div :class="c.tipo_documento === 'NIT' ? 'col-span-3' : ''">
                                                    <TextInput v-model="c.numero_documento" class="mt-1 block w-full" required />
                                                </div>
                                                <div v-if="c.tipo_documento === 'NIT'">
                                                    <TextInput v-model="c.dv" maxlength="1" placeholder="DV" class="mt-1 block w-full px-0 text-center" />
                                                </div>
                                            </div>
                                            <InputError :message="form.errors[`codeudores.${i}.numero_documento`]" />
                                            <InputError :message="form.errors[`codeudores.${i}.dv`]" />
                                        </div>
                                        <div>
                                            <InputLabel value="Celular" />
                                            <div class="relative">
                                                <PhoneIcon class="pointer-events-none absolute left-3 top-3.5 h-4 w-4 text-gray-400" />
                                                <TextInput v-model="c.celular" class="mt-1 block w-full pl-10" placeholder="Ej: 3001234567" />
                                            </div>
                                        </div>
                                        <div class="md:col-span-2">
                                            <InputLabel value="Correo electronico" />
                                            <div class="relative">
                                                <EnvelopeIcon class="pointer-events-none absolute left-3 top-3.5 h-4 w-4 text-gray-400" />
                                                <TextInput v-model="c.correo" type="email" class="mt-1 block w-full pl-10" placeholder="correo@ejemplo.com" />
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-5 grid grid-cols-1 gap-5 border-t border-gray-200 pt-5 dark:border-gray-700 xl:grid-cols-2">
                                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/50">
                                            <div class="mb-3 flex items-center justify-between gap-3">
                                                <div>
                                                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">Ubicaciones</p>
                                                    <p class="mt-1 text-xs font-semibold text-gray-500 dark:text-gray-400">Direcciones conocidas del codeudor.</p>
                                                </div>
                                                <button type="button" @click="addAddress(i)" class="inline-flex items-center rounded-lg border border-indigo-200 bg-white px-3 py-2 text-[10px] font-black uppercase text-indigo-600 transition hover:bg-indigo-50 dark:border-indigo-900/60 dark:bg-gray-800 dark:text-indigo-300">
                                                    <MapPinIcon class="mr-1.5 h-3.5 w-3.5" /> Añadir
                                                </button>
                                            </div>

                                            <div v-if="!c.addresses?.length" class="rounded-lg border border-dashed border-gray-300 p-5 text-center text-xs font-bold text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                                Sin ubicaciones registradas.
                                            </div>
                                            <div v-else class="space-y-3">
                                                <div v-for="(addr, aIdx) in c.addresses" :key="aIdx" class="relative rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                                                    <button type="button" @click="removeAddress(i, aIdx)" class="absolute right-2 top-2 rounded-md p-1.5 text-rose-500 transition hover:bg-rose-50 dark:hover:bg-rose-950/30" title="Eliminar ubicacion">
                                                        <TrashIcon class="h-3.5 w-3.5" />
                                                    </button>
                                                    <div class="grid grid-cols-1 gap-3 pr-8 sm:grid-cols-3">
                                                        <div>
                                                            <InputLabel value="Etiqueta" class="!text-[9px]" />
                                                            <TextInput v-model="addr.label" placeholder="Casa, oficina" class="w-full !text-xs" />
                                                        </div>
                                                        <div class="sm:col-span-2">
                                                            <InputLabel value="Direccion" class="!text-[9px]" />
                                                            <TextInput v-model="addr.address" placeholder="Calle, carrera, barrio" class="w-full !text-xs" />
                                                        </div>
                                                        <div class="sm:col-span-3">
                                                            <InputLabel value="Ciudad" class="!text-[9px]" />
                                                            <TextInput v-model="addr.city" placeholder="Ciudad" class="w-full !text-xs" />
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/50">
                                            <div class="mb-3 flex items-center justify-between gap-3">
                                                <div>
                                                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">Enlaces digitales</p>
                                                    <p class="mt-1 text-xs font-semibold text-gray-500 dark:text-gray-400">Perfiles, portales o datos de rastreo utiles.</p>
                                                </div>
                                                <button type="button" @click="c.social_links.push({ label: 'Facebook', url: '' })" class="inline-flex items-center rounded-lg border border-sky-200 bg-white px-3 py-2 text-[10px] font-black uppercase text-sky-600 transition hover:bg-sky-50 dark:border-sky-900/60 dark:bg-gray-800 dark:text-sky-300">
                                                    <LinkIcon class="mr-1.5 h-3.5 w-3.5" /> Añadir
                                                </button>
                                            </div>

                                            <div v-if="!c.social_links?.length" class="rounded-lg border border-dashed border-gray-300 p-5 text-center text-xs font-bold text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                                Sin enlaces digitales registrados.
                                            </div>
                                            <div v-else class="space-y-3">
                                                <div v-for="(link, sIdx) in c.social_links" :key="sIdx" class="relative rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                                                    <button type="button" @click="c.social_links.splice(sIdx, 1)" class="absolute right-2 top-2 rounded-md p-1.5 text-rose-500 transition hover:bg-rose-50 dark:hover:bg-rose-950/30" title="Eliminar enlace">
                                                        <TrashIcon class="h-3.5 w-3.5" />
                                                    </button>
                                                    <div class="grid grid-cols-1 gap-3 pr-8 sm:grid-cols-3">
                                                        <div>
                                                            <InputLabel value="Etiqueta" class="!text-[9px]" />
                                                            <TextInput v-model="link.label" placeholder="Facebook, LinkedIn" class="w-full !text-xs" />
                                                        </div>
                                                        <div class="sm:col-span-2">
                                                            <InputLabel value="URL / referencia" class="!text-[9px]" />
                                                            <TextInput v-model="link.url" type="text" placeholder="https://..." class="w-full !text-xs" />
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <!-- TAB 4 -->
                            <section v-show="activeTab === 'control-notas'" class="space-y-8">
                                <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900 sm:p-6"><InputLabel value="Notas Legales / Internas" /><Textarea v-model="form.notas_legales" rows="5" class="w-full" /><InputError :message="form.errors.notas_legales" /></div>
                                <div v-if="user.tipo_usuario === 'admin'" class="rounded-2xl border border-red-200 bg-red-50 p-4 dark:border-red-900/60 dark:bg-red-900/20 sm:p-6">
                                    <div class="flex items-center justify-between">
                                        <div><h4 class="font-bold text-red-800 dark:text-red-200">{{ caso.nota_cierre ? 'Reabrir' : 'Cerrar' }} Caso</h4></div>
                                        <PrimaryButton v-if="caso.nota_cierre" @click="submitReopen" class="bg-blue-600">Reabrir</PrimaryButton>
                                        <DangerButton v-else @click="confirmClose">Cerrar</DangerButton>
                                    </div>
                                </div>
                            </section>
                        </fieldset>
                    </div>
                </div>
            </div>
        </div>

        <Modal :show="showCloseModal" @close="showCloseModal = false" centered>
            <div class="p-6">
                <h2 class="text-lg font-bold">Cierre del Caso</h2>
                <div class="mt-4"><InputLabel value="Nota Final" /><Textarea v-model="closeForm.nota_cierre" class="w-full" rows="4" /></div>
                <div class="mt-6 flex justify-end gap-3"><SecondaryButton @click="showCloseModal = false">Cerrar</SecondaryButton><DangerButton @click="submitClose">Confirmar Cierre</DangerButton></div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<style scoped>
.case-edit-page :deep(label),
.case-edit-page :deep(.block.font-medium) {
    margin-bottom: 0.4rem;
    color: rgb(55 65 81);
    font-size: 0.75rem;
    font-weight: 900;
    letter-spacing: 0.04em;
}

.dark .case-edit-page :deep(label),
.dark .case-edit-page :deep(.block.font-medium) {
    color: rgb(209 213 219);
}

.case-edit-page :deep(input:not([type="checkbox"]):not([type="radio"])),
.case-edit-page :deep(textarea),
.case-edit-page :deep(select) {
    width: 100%;
    min-width: 0;
    border-radius: 0.75rem;
    border-color: rgb(209 213 219);
    background-color: rgb(255 255 255);
    color: rgb(17 24 39);
    font-size: 0.875rem;
    font-weight: 650;
    box-shadow: 0 1px 2px rgb(15 23 42 / 0.04);
}

.case-edit-page :deep(input:not([type="checkbox"]):not([type="radio"])),
.case-edit-page :deep(select) {
    min-height: 2.75rem;
}

.case-edit-page :deep(textarea) {
    min-height: 7rem;
    resize: vertical;
}

.case-edit-page :deep(input:focus),
.case-edit-page :deep(textarea:focus),
.case-edit-page :deep(select:focus) {
    border-color: rgb(79 70 229);
    box-shadow: 0 0 0 3px rgb(99 102 241 / 0.14);
}

.dark .case-edit-page :deep(input:not([type="checkbox"]):not([type="radio"])),
.dark .case-edit-page :deep(textarea),
.dark .case-edit-page :deep(select) {
    border-color: rgb(55 65 81);
    background-color: rgb(17 24 39);
    color: rgb(243 244 246);
}

.case-edit-page :deep(.relative.w-full > .min-h-\[42px\]) {
    min-height: 2.75rem;
    border-radius: 0.75rem;
}

.case-edit-page :deep(.text-red-600),
.case-edit-page :deep(.text-red-500) {
    overflow-wrap: anywhere;
}
</style>
