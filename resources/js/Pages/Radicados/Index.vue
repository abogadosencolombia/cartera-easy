<script setup>
import { ref, watch, computed, onMounted } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TextInput from '@/Components/TextInput.vue';
import SelectInput from '@/Components/SelectInput.vue';
import AsyncSelect from '@/Components/AsyncSelect.vue';
import Pagination from '@/Components/Pagination.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import Textarea from '@/Components/Textarea.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PersonaCompletenessIndicator from '@/Components/PersonaCompletenessIndicator.vue';
import { hasIncompletePersonaInfo } from '@/Utils/personaCompleteness';
import { debounce } from 'lodash';
import { useProcesos } from '@/composables/useProcesos';
import AppAlert from '@/Utils/appAlert';
import { 
    MagnifyingGlassIcon, 
    FunnelIcon, 
    DocumentArrowDownIcon, 
    PlusIcon, 
    EllipsisVerticalIcon,
    ScaleIcon,
    CalendarDaysIcon,
    MapPinIcon,
    ExclamationTriangleIcon,
    CheckCircleIcon,
    ClockIcon,
    ArrowPathIcon,
    BuildingLibraryIcon,
    ChevronDownIcon,
    XMarkIcon,
    DocumentDuplicateIcon,
    EyeIcon,
    TrashIcon,
    ArrowDownTrayIcon,
    ArchiveBoxXMarkIcon,
    InboxIcon,
    CloudArrowUpIcon,
    UserIcon,
    UserGroupIcon,
    BuildingOfficeIcon,
    PhoneIcon,
    EnvelopeIcon,
    ClipboardDocumentListIcon,
    LinkIcon,
    PencilSquareIcon,
    MapPinIcon as PinIcon,
    HandThumbUpIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    procesos: { type: Object, required: true },
    filtros: { type: Object, default: () => ({}) },
    juzgados: { type: Array, default: () => [] },
    tiposProceso: { type: Array, default: () => [] },
    selectedJuzgado: { type: Object, default: null },
    stats: { type: Object, default: () => ({}) },
});

const { getRevisionStatus } = useProcesos();

// --- LÓGICA DE VISTA RÁPIDA ---
const selectedProceso = ref(null);
const showQuickViewModal = ref(false);

const openQuickView = (proceso) => {
    selectedProceso.value = proceso;
    showQuickViewModal.value = true;
};

const closeQuickView = () => {
    showQuickViewModal.value = false;
    setTimeout(() => { selectedProceso.value = null; }, 300);
};

watch(() => props.procesos.data, (procesos) => {
    if (!selectedProceso.value) return;

    const refreshed = procesos.find(p => p.id === selectedProceso.value.id);
    if (refreshed) {
        selectedProceso.value = refreshed;
        return;
    }

    closeQuickView();
});

const currentIndex = computed(() => {
    if (!selectedProceso.value) return -1;
    return props.procesos.data.findIndex(p => p.id === selectedProceso.value.id);
});

const nextProceso = () => {
    const idx = currentIndex.value;
    if (idx < props.procesos.data.length - 1) {
        selectedProceso.value = props.procesos.data[idx + 1];
    }
};

const prevProceso = () => {
    const idx = currentIndex.value;
    if (idx > 0) {
        selectedProceso.value = props.procesos.data[idx - 1];
    }
};

// --- TIPOS DE ENTIDAD ---
const tiposEntidad = [
    'Juzgado', 'Fiscalía', 'Secretaría', 'Despacho', 'Centro de Servicios',
    'Corte', 'Tribunal', 'Notaría', 'Superintendencia'
];

// --- FILTROS ---
const filterStorageKey = 'radicados.index.filters';
const filterKeys = ['search', 'estado', 'juzgado_id', 'tipo_proceso_id', 'tipo_entidad', 'sin_radicado', 'solo_vencidos', 'cerrados', 'actualizados_hoy', 'integridad_baja'];
const booleanFilterKeys = ['sin_radicado', 'solo_vencidos', 'cerrados', 'actualizados_hoy', 'integridad_baja'];

const parseBooleanFilter = (value) => value === true || value === 'true' || value === 1 || value === '1';

const readStoredFilters = () => {
    if (typeof window === 'undefined') return {};

    try {
        return JSON.parse(sessionStorage.getItem(filterStorageKey) || '{}') || {};
    } catch {
        return {};
    }
};

const storedFilters = readStoredFilters();

const initialFilterValue = (key, fallback = '') => props.filtros?.[key] ?? storedFilters[key] ?? fallback;

const hasUsefulFilterValue = (filters) => filterKeys.some((key) => {
    if (key === 'estado') {
        return String(filters[key] ?? '').trim() !== '' && filters[key] !== 'TODOS';
    }

    if (booleanFilterKeys.includes(key)) {
        return parseBooleanFilter(filters[key]);
    }

    return String(filters[key] ?? '').trim() !== '';
});

const hasUsefulUrlFilter = () => {
    if (typeof window === 'undefined') return false;

    const params = new URLSearchParams(window.location.search);
    return filterKeys.some((key) => {
        if (!params.has(key)) return false;
        if (key === 'estado') {
            const value = params.get(key);
            return value !== '' && value !== 'TODOS';
        }
        if (booleanFilterKeys.includes(key)) {
            return parseBooleanFilter(params.get(key));
        }

        return String(params.get(key) ?? '').trim() !== '';
    });
};

const search = ref(initialFilterValue('search'));
const estado = ref(initialFilterValue('estado') || 'TODOS');
const juzgadoId = ref(initialFilterValue('juzgado_id'));
const selectedJuzgado = ref(props.selectedJuzgado);
const tipoProcesoId = ref(initialFilterValue('tipo_proceso_id'));
const tipoEntidad = ref(initialFilterValue('tipo_entidad'));
const sinRadicado = ref(parseBooleanFilter(initialFilterValue('sin_radicado', false)));
const soloVencidos = ref(parseBooleanFilter(initialFilterValue('solo_vencidos', false)));
const cerrados = ref(parseBooleanFilter(initialFilterValue('cerrados', false)));
const actualizadosHoy = ref(parseBooleanFilter(initialFilterValue('actualizados_hoy', false)));
const integridadBaja = ref(parseBooleanFilter(initialFilterValue('integridad_baja', false)));

const currentFilterPayload = () => ({
    search: search.value,
    estado: estado.value === 'TODOS' ? '' : estado.value,
    juzgado_id: juzgadoId.value,
    tipo_proceso_id: tipoProcesoId.value,
    tipo_entidad: tipoEntidad.value,
    sin_radicado: sinRadicado.value,
    solo_vencidos: soloVencidos.value,
    cerrados: cerrados.value,
    actualizados_hoy: actualizadosHoy.value,
    integridad_baja: integridadBaja.value,
});

const persistFilters = (filters) => {
    if (typeof window === 'undefined') return;

    if (hasUsefulFilterValue(filters)) {
        sessionStorage.setItem(filterStorageKey, JSON.stringify(filters));
    } else {
        sessionStorage.removeItem(filterStorageKey);
    }
};

const reapplyStoredFiltersIfNeeded = () => {
    if (!hasUsefulUrlFilter() && hasUsefulFilterValue(storedFilters)) {
        router.get(route('procesos.index'), storedFilters, {
            replace: true,
            preserveState: false,
        });
        return true;
    }

    return false;
};

watch(selectedJuzgado, (val) => {
    juzgadoId.value = val?.id || '';
});

const clearControlFilters = () => {
    estado.value = 'TODOS';
    sinRadicado.value = false;
    soloVencidos.value = false;
    cerrados.value = false;
    actualizadosHoy.value = false;
    integridadBaja.value = false;
};

const applyControlFilter = (filterKey) => {
    const filterRefs = {
        sin_radicado: sinRadicado,
        solo_vencidos: soloVencidos,
        cerrados,
        actualizados_hoy: actualizadosHoy,
        integridad_baja: integridadBaja,
    };
    const target = filterRefs[filterKey];
    if (!target) return;

    const nextValue = !target.value;
    clearControlFilters();
    target.value = nextValue;
};

const isDirty = computed(() => {
    return search.value !== '' || estado.value !== 'TODOS' || juzgadoId.value !== '' || tipoProcesoId.value !== '' || tipoEntidad.value !== '' || sinRadicado.value === true || soloVencidos.value === true || cerrados.value === true || actualizadosHoy.value === true || integridadBaja.value === true;
});

const resetFilters = () => {
    if (typeof window !== 'undefined') {
        sessionStorage.removeItem(filterStorageKey);
    }

    search.value = '';
    estado.value = 'TODOS';
    juzgadoId.value = '';
    selectedJuzgado.value = null;
    tipoProcesoId.value = '';
    tipoEntidad.value = '';
    sinRadicado.value = false;
    soloVencidos.value = false;
    cerrados.value = false;
    actualizadosHoy.value = false;
    integridadBaja.value = false;
};

const applyFilters = debounce(() => {
    const filters = currentFilterPayload();
    persistFilters(filters);

    router.get(route('procesos.index'), filters, { preserveState: true, replace: true });
}, 300);

watch([search, estado, juzgadoId, tipoProcesoId, tipoEntidad, sinRadicado, soloVencidos, cerrados, actualizadosHoy, integridadBaja], applyFilters);

onMounted(() => {
    reapplyStoredFiltersIfNeeded();
});

// --- EXPORTAR ---
const exportarExcel = () => {
    const params = new URLSearchParams(currentFilterPayload());
    window.location.href = route('procesos.exportar') + '?' + params.toString();
};

// --- UTILIDADES ---
const copyToClipboard = (text) => {
    if (!text) return;
    navigator.clipboard.writeText(text);
};

const getInactivityDays = (dateString) => {
    if (!dateString) return 0;
    const lastUpdate = new Date(dateString);
    const now = new Date();
    const diff = now - lastUpdate;
    return Math.floor(diff / (1000 * 60 * 60 * 24));
};

// --- MODALES ---
const procesoToManage = ref(null);
const showCloseModal = ref(false);
const showReopenModal = ref(false);
const closeForm = useForm({ nota_cierre: '' });
const reopenForm = useForm({});

const openCloseModal = (proceso) => {
    procesoToManage.value = proceso;
    closeForm.reset();
    showCloseModal.value = true;
};

const openReopenModal = (proceso) => {
    procesoToManage.value = proceso;
    showReopenModal.value = true;
};

const submitCloseCase = () => {
    closeForm.patch(route('procesos.close', procesoToManage.value.id), {
        preserveScroll: true,
        onSuccess: () => { showCloseModal.value = false; procesoToManage.value = null; }
    });
};

const submitReopenCase = () => {
    reopenForm.patch(route('procesos.reopen', procesoToManage.value.id), {
        preserveScroll: true,
        onSuccess: () => { showReopenModal.value = false; procesoToManage.value = null; }
    });
};

const togglePin = (proceso) => {
    router.patch(route('procesos.pin', proceso.id), {}, {
        preserveScroll: true,
    });
};

const quickReview = (proceso) => {
    AppAlert.fire({
        title: '¿Marcar como revisado?',
        text: 'Se actualizará la revisión a HOY y se programará la próxima en 15 días.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#4f46e5',
        confirmButtonText: 'Sí, confirmar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            router.patch(route('procesos.quick_review', proceso.id), {}, {
                preserveScroll: true,
            });
        }
    });
};

const deleteProceso = (proceso) => {
    AppAlert.fire({
        title: '¿Suspender Expediente?',
        html: `
            <div class="text-left space-y-3">
                <p class="text-sm text-gray-600 font-medium">Vas a mover el radicado <b>${proceso.radicado || 'ID #'+proceso.id}</b> a la papelera.</p>
                <div class="p-3 bg-amber-50 border border-amber-100 rounded-lg">
                    <p class="text-[11px] text-amber-700 font-black uppercase tracking-tight mb-1">⚠️ ATENCIÓN:</p>
                    <ul class="text-[10px] text-amber-600 space-y-1 list-disc pl-4 font-bold">
                        <li>El expediente ya no aparecerá en la lista de activos.</li>
                        <li>Toda la trazabilidad y documentos <span class="underline">SE MANTIENEN</span>.</li>
                        <li>Podrás restaurarlo si es necesario.</li>
                    </ul>
                </div>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Sí, suspender expediente',
        cancelButtonText: 'Cancelar',
        customClass: {
            confirmButton: 'rounded-xl font-black text-[10px] uppercase tracking-widest px-6 py-3',
            cancelButton: 'rounded-xl font-black text-[10px] uppercase tracking-widest px-6 py-3'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('procesos.destroy', proceso.id), {
                preserveScroll: true,
                onSuccess: () => AppAlert.fire('Suspendido', 'El proceso ha sido movido a la papelera.', 'success')
            });
        }
    });
};

// --- HELPERS ---
const formatNames = (personas) => {
    if (!personas || personas.length === 0) return 'Sin asignar';
    if (personas.length <= 2) return personas.map(p => p.nombre_completo).join(', ');
    return `${personas[0].nombre_completo}, ${personas[1].nombre_completo} y ${personas.length - 2} más`;
};

const formatDate = (dateString) => {
    if (!dateString) return null;
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return dateString;
    return new Intl.DateTimeFormat('es-CO', { day: 'numeric', month: 'short', year: 'numeric' }).format(date);
};

const getEtapaColor = (riesgo) => {
    switch (riesgo) {
        case 'MUY_ALTO': return 'bg-red-100 text-red-800 border-red-200 dark:bg-red-900/30 dark:text-red-300';
        case 'ALTO': return 'bg-orange-100 text-orange-800 border-orange-200 dark:bg-orange-900/30 dark:text-orange-300';
        case 'MEDIO': return 'bg-yellow-100 text-yellow-800 border-yellow-200 dark:bg-yellow-900/30 dark:text-yellow-300';
        default: return 'bg-green-100 text-green-800 border-green-200 dark:bg-green-900/30 dark:text-green-300';
    }
};

const getVencimientoInfo = (proceso) => {
    if (proceso.dias_para_vencer === null || proceso.dias_para_vencer === undefined) return null;
    const dias = proceso.dias_para_vencer;
    if (dias < 0) return { text: `Vencido hace ${Math.abs(dias)} días`, class: 'text-red-600 font-bold' };
    if (dias === 0) return { text: 'Vence HOY', class: 'text-orange-600 font-bold' };
    return { text: `Vence en ${dias} días`, class: 'text-gray-500' };
};

const activeFilterCount = computed(() => filterKeys.reduce((count, key) => {
    if (key === 'estado') {
        return count + (estado.value !== 'TODOS' ? 1 : 0);
    }

    if (booleanFilterKeys.includes(key)) {
        const refMap = {
            sin_radicado: sinRadicado,
            solo_vencidos: soloVencidos,
            cerrados,
            actualizados_hoy: actualizadosHoy,
            integridad_baja: integridadBaja,
        };
        return count + (refMap[key]?.value ? 1 : 0);
    }

    const refMap = { search, juzgado_id: juzgadoId, tipo_proceso_id: tipoProcesoId, tipo_entidad: tipoEntidad };
    return count + (String(refMap[key]?.value ?? '').trim() !== '' ? 1 : 0);
}, 0));

const resultRange = computed(() => {
    const total = props.procesos?.total || 0;
    if (!total) return '0 expedientes';

    const from = props.procesos?.from || 1;
    const to = props.procesos?.to || props.procesos?.data?.length || total;
    return `${from}-${to} de ${total}`;
});

const summaryCards = computed(() => [
    { key: 'total', label: 'Total expedientes', value: props.stats?.total ?? '...', description: 'En seguimiento', icon: ScaleIcon, iconClass: 'text-slate-500', filterKey: null },
    { key: 'sin_radicado', label: 'Sin radicado', value: props.stats?.sin_radicado ?? '...', description: 'Pendientes de número', icon: ArchiveBoxXMarkIcon, iconClass: 'text-amber-500', filterKey: 'sin_radicado', ref: sinRadicado, activeClass: 'ring-2 ring-amber-300 border-amber-200 bg-amber-50/70 dark:border-amber-500/60 dark:bg-amber-900/10' },
    { key: 'solo_vencidos', label: 'Revisión vencida', value: props.stats?.vencidos ?? '...', description: 'Acción requerida', icon: ExclamationTriangleIcon, iconClass: 'text-rose-500', filterKey: 'solo_vencidos', ref: soloVencidos, activeClass: 'ring-2 ring-rose-300 border-rose-200 bg-rose-50/70 dark:border-rose-500/60 dark:bg-rose-900/10' },
    { key: 'revisar_hoy', label: 'Revisar hoy', value: props.stats?.revisar_hoy ?? '...', description: 'Agenda del día', icon: ClockIcon, iconClass: 'text-teal-500', filterKey: null },
    { key: 'integridad_baja', label: 'Integridad baja', value: props.stats?.integridad_baja ?? '...', description: 'Datos por completar', icon: ExclamationTriangleIcon, iconClass: 'text-orange-500', filterKey: 'integridad_baja', ref: integridadBaja, activeClass: 'ring-2 ring-orange-300 border-orange-200 bg-orange-50/70 dark:border-orange-500/60 dark:bg-orange-900/10' },
    { key: 'actualizados_hoy', label: 'Actualizados hoy', value: props.stats?.actualizados_hoy ?? '...', description: 'Movimientos recientes', icon: ArrowPathIcon, iconClass: 'text-indigo-500', filterKey: 'actualizados_hoy', ref: actualizadosHoy, activeClass: 'ring-2 ring-indigo-300 border-indigo-200 bg-indigo-50/70 dark:border-indigo-500/60 dark:bg-indigo-900/10' },
]);

const controlFilterItems = computed(() => [
    { key: 'sin_radicado', label: 'Sin radicado', count: props.stats?.sin_radicado, ref: sinRadicado, icon: ArchiveBoxXMarkIcon, activeClass: 'bg-amber-600 text-white border-amber-600' },
    { key: 'solo_vencidos', label: 'Revisión vencida', count: props.stats?.vencidos, ref: soloVencidos, icon: ExclamationTriangleIcon, activeClass: 'bg-rose-600 text-white border-rose-600' },
    { key: 'integridad_baja', label: 'Integridad baja', count: props.stats?.integridad_baja, ref: integridadBaja, icon: ExclamationTriangleIcon, activeClass: 'bg-orange-600 text-white border-orange-600' },
    { key: 'cerrados', label: 'Cerrados', count: props.stats?.cerrados, ref: cerrados, icon: CheckCircleIcon, activeClass: 'bg-emerald-600 text-white border-emerald-600' },
    { key: 'actualizados_hoy', label: 'Actualizados hoy', count: props.stats?.actualizados_hoy, ref: actualizadosHoy, icon: ArrowPathIcon, activeClass: 'bg-indigo-600 text-white border-indigo-600' },
]);

const userInitials = (name) => {
    if (!name) return 'SA';
    return name.split(' ').filter(Boolean).map(part => part[0]).join('').slice(0, 2).toUpperCase();
};

const firstName = (name) => name ? name.split(' ')[0] : 'Sin asignar';
const primaryParty = (personas) => personas?.[0]?.nombre_completo || 'Sin registro';
const extraPartiesCount = (personas) => Math.max((personas?.length || 0) - 1, 0);
const firstIncompleteParty = (personas) => personas?.find(hasIncompletePersonaInfo) || null;
const processTypeName = (proceso) => proceso.tipo_proceso?.nombre || 'General';
const processOfficeName = (proceso) => proceso.entidad || proceso.juzgado?.nombre || 'Despacho por definir';
const integrityScore = (proceso) => Number(proceso.integridad_score || 0);
const integrityBarStyle = (proceso) => ({ width: `${Math.min(Math.max(integrityScore(proceso), 0), 100)}%` });
const integrityTone = (proceso) => {
    const score = integrityScore(proceso);
    if (score >= 85) return 'bg-emerald-500';
    if (score >= 60) return 'bg-amber-500';
    return 'bg-rose-500';
};
const isInactiveProcess = (proceso) => getInactivityDays(proceso.updated_at) >= 30 && proceso.estado !== 'CERRADO';
const isControlActive = (item) => Boolean(item.ref?.value);

const copyLegalInfo = (proceso) => {
    const p = proceso || selectedProceso.value;
    if (!p) return;
    
    const radicado = p.radicado || 'SIN RADICADO';
    const juzgado = p.entidad || 'POR DEFINIR';
    const demandantes = p.demandantes?.map(d => d.nombre_completo).join(', ') || 'SIN REGISTRO';
    const demandados = p.demandados?.map(d => d.nombre_completo).join(', ') || 'SIN REGISTRO';
    const asunto = p.asunto || 'N/A';
    
    let text = `EXPEDIENTE JUDICIAL\n`;
    text += `--------------------\n`;
    text += `Radicado: ${radicado}\n`;
    text += `Juzgado: ${juzgado}\n`;
    text += `Demandante(s): ${demandantes}\n`;
    text += `Demandado(s): ${demandados}\n`;
    text += `Asunto: ${asunto}\n`;
    
    navigator.clipboard.writeText(text).then(() => {
        AppAlert.fire({
            title: '¡Copiado!',
            text: 'Información lista para pegar.',
            icon: 'success',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2500,
            timerProgressBar: true,
            background: '#EEF2FF',
            iconColor: '#4F46E5',
            color: '#312E81',
        });
    });
};
</script>

<template>
    <Head title="Gestión de Radicados" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="mx-auto max-w-[1600px] space-y-4 px-4 sm:px-6 lg:px-8">
                <!-- Encabezado y Acciones Principales -->
                <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:p-5">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex min-w-0 items-center gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-300">
                                <ScaleIcon class="h-6 w-6" />
                            </div>
                            <div class="min-w-0">
                                <h2 class="text-xl font-black tracking-tight text-gray-950 dark:text-white sm:text-2xl">Expedientes Judiciales</h2>
                                <p class="mt-1 text-sm font-medium text-gray-500 dark:text-gray-400">Radicados, partes, despacho y revisión procesal en una sola vista.</p>
                            </div>
                        </div>

                        <div class="grid w-full grid-cols-1 gap-2 sm:grid-cols-3 lg:w-auto">
                            <Link
                                :href="route('procesos.import.show')"
                                class="inline-flex items-center justify-center gap-2 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-2.5 text-xs font-black uppercase tracking-widest text-indigo-700 shadow-sm transition-colors hover:border-indigo-300 hover:bg-indigo-100 dark:border-indigo-900/60 dark:bg-indigo-950/30 dark:text-indigo-300"
                            >
                                <CloudArrowUpIcon class="h-4 w-4" />
                                Carga asistida
                            </Link>
                            <button
                                type="button"
                                @click="exportarExcel"
                                class="inline-flex items-center justify-center gap-2 rounded-lg border border-emerald-600 bg-emerald-600 px-4 py-2.5 text-xs font-black uppercase tracking-widest text-white shadow-sm transition-colors hover:bg-emerald-700"
                            >
                                <ArrowDownTrayIcon class="h-4 w-4" />
                                Exportar
                            </button>
                            <Link
                                :href="route('procesos.create')"
                                class="inline-flex items-center justify-center gap-2 rounded-lg border border-indigo-600 bg-indigo-600 px-4 py-2.5 text-xs font-black uppercase tracking-widest text-white shadow-sm transition-colors hover:bg-indigo-700"
                            >
                                <PlusIcon class="h-4 w-4" />
                                Nuevo radicado
                            </Link>
                        </div>
                    </div>
                </section>

                <!-- Indicadores -->
                <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                    <template v-for="card in summaryCards" :key="card.key">
                        <button
                            v-if="card.filterKey"
                            type="button"
                            @click="applyControlFilter(card.filterKey)"
                            :class="isControlActive(card) ? card.activeClass : ''"
                            class="rounded-lg border border-gray-200 bg-white p-4 text-left shadow-sm transition-all hover:border-indigo-200 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-indigo-300 dark:border-gray-700 dark:bg-gray-800"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">{{ card.label }}</p>
                                    <p class="mt-1 break-words text-2xl font-black text-gray-950 dark:text-white">{{ card.value }}</p>
                                    <p class="mt-1 text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ card.description }}</p>
                                </div>
                                <component :is="card.icon" class="h-5 w-5 shrink-0" :class="card.iconClass" />
                            </div>
                        </button>
                        <div v-else class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">{{ card.label }}</p>
                                    <p class="mt-1 break-words text-2xl font-black text-gray-950 dark:text-white">{{ card.value }}</p>
                                    <p class="mt-1 text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ card.description }}</p>
                                </div>
                                <component :is="card.icon" class="h-5 w-5 shrink-0" :class="card.iconClass" />
                            </div>
                        </div>
                    </template>
                </section>

                <!-- Filtros -->
                <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:p-5">
                    <div class="flex flex-col gap-3 border-b border-gray-100 pb-4 dark:border-gray-700 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-gray-50 text-gray-500 dark:bg-gray-900 dark:text-gray-300">
                                <FunnelIcon class="h-5 w-5" />
                            </div>
                            <div>
                                <h3 class="text-sm font-black uppercase tracking-tight text-gray-950 dark:text-white">Filtros de radicados</h3>
                                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400">
                                    {{ resultRange }}<span v-if="activeFilterCount"> · {{ activeFilterCount }} filtro{{ activeFilterCount === 1 ? '' : 's' }} activo{{ activeFilterCount === 1 ? '' : 's' }}</span>
                                </p>
                            </div>
                        </div>
                        <button
                            v-if="isDirty"
                            type="button"
                            @click="resetFilters"
                            class="inline-flex w-fit items-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-[10px] font-black uppercase tracking-widest text-rose-700 transition-colors hover:bg-rose-100 dark:border-rose-900/50 dark:bg-rose-900/10 dark:text-rose-300"
                        >
                            <XMarkIcon class="h-4 w-4" />
                            Limpiar filtros
                        </button>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-12">
                        <div class="lg:col-span-4">
                            <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-gray-400">Búsqueda general</label>
                            <div class="relative">
                                <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                                <input
                                    v-model="search"
                                    type="text"
                                    class="block w-full rounded-lg border border-gray-200 bg-gray-50 py-2.5 pl-9 pr-3 text-sm font-semibold text-gray-800 transition-colors focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                    placeholder="Radicado, asunto, demandante, demandado o documento"
                                />
                            </div>
                        </div>

                        <div class="lg:col-span-2">
                            <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-gray-400">Estado</label>
                            <SelectInput v-model="estado" class="w-full rounded-lg py-2.5 text-sm">
                                <option value="TODOS">Todos</option>
                                <option value="ACTIVO">Activos</option>
                                <option value="CERRADO">Cerrados</option>
                            </SelectInput>
                        </div>

                        <div class="lg:col-span-3">
                            <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-gray-400">Tipo de proceso</label>
                            <SelectInput v-model="tipoProcesoId" class="w-full rounded-lg py-2.5 text-sm">
                                <option value="">Todos</option>
                                <option v-for="tipo in tiposProceso" :key="tipo.id" :value="String(tipo.id)">{{ tipo.nombre }}</option>
                            </SelectInput>
                        </div>

                        <div class="lg:col-span-2">
                            <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-gray-400">Tipo de entidad</label>
                            <SelectInput v-model="tipoEntidad" class="w-full rounded-lg py-2.5 text-sm">
                                <option value="">Todas</option>
                                <option v-for="tipo in tiposEntidad" :key="tipo" :value="tipo">{{ tipo }}</option>
                            </SelectInput>
                        </div>

                        <div class="lg:col-span-12 xl:col-span-3">
                            <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-gray-400">Despacho o juzgado</label>
                            <AsyncSelect
                                v-model="selectedJuzgado"
                                :endpoint="route('juzgados.search')"
                                placeholder="Buscar juzgado o despacho"
                                label-key="nombre"
                                class="!min-h-[42px] !rounded-lg"
                            />
                        </div>

                        <div class="lg:col-span-12">
                            <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-gray-400">Control rápido</label>
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-5">
                                <button
                                    v-for="item in controlFilterItems"
                                    :key="item.key"
                                    type="button"
                                    @click="applyControlFilter(item.key)"
                                    :class="isControlActive(item) ? item.activeClass : 'border-gray-200 bg-gray-50 text-gray-600 hover:border-indigo-200 hover:text-indigo-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300'"
                                    class="inline-flex min-h-10 items-center justify-between gap-2 rounded-lg border px-3 py-2 text-left text-[10px] font-black uppercase tracking-wider transition-colors"
                                >
                                    <span class="inline-flex min-w-0 items-center gap-2">
                                        <component :is="item.icon" class="h-4 w-4 shrink-0" />
                                        <span class="truncate">{{ item.label }}</span>
                                    </span>
                                    <span v-if="item.count !== null && item.count !== undefined" class="shrink-0 rounded bg-white/70 px-1.5 py-0.5 text-[9px] text-gray-700 dark:bg-black/20 dark:text-white">{{ item.count }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Listado de Radicados -->
                <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex flex-col gap-3 border-b border-gray-100 p-4 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-sm font-black uppercase tracking-tight text-gray-950 dark:text-white">Listado de expedientes judiciales</h3>
                            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400">{{ resultRange }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">
                            <span class="rounded-lg border border-gray-200 px-2.5 py-1.5 dark:border-gray-700">Click para vista rápida</span>
                            <span v-if="isDirty" class="rounded-lg bg-indigo-50 px-2.5 py-1.5 text-indigo-700 dark:bg-indigo-900/20 dark:text-indigo-300">Filtrado</span>
                        </div>
                    </div>

                    <div v-if="procesos.data.length === 0" class="flex min-h-72 flex-col items-center justify-center p-8 text-center">
                        <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-lg bg-gray-50 dark:bg-gray-900">
                            <InboxIcon class="h-8 w-8 text-gray-400" />
                        </div>
                        <h3 class="text-base font-black text-gray-950 dark:text-white">No se encontraron expedientes</h3>
                        <p class="mt-1 max-w-sm text-sm font-medium text-gray-500 dark:text-gray-400">No hay registros que coincidan con los filtros aplicados.</p>
                        <button v-if="isDirty" type="button" @click="resetFilters" class="mt-4 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-2 text-xs font-black uppercase tracking-widest text-indigo-700 hover:bg-indigo-100">
                            Limpiar filtros
                        </button>
                    </div>

                    <template v-else>
                        <div class="hidden overflow-x-auto custom-scrollbar-horizontal md:block">
                            <table class="min-w-[1220px] divide-y divide-gray-100 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-900/40">
                                    <tr>
                                        <th scope="col" class="px-4 py-3 text-left text-[10px] font-black uppercase tracking-widest text-gray-400">Radicado y asunto</th>
                                        <th scope="col" class="px-4 py-3 text-left text-[10px] font-black uppercase tracking-widest text-gray-400">Partes</th>
                                        <th scope="col" class="px-4 py-3 text-left text-[10px] font-black uppercase tracking-widest text-gray-400">Despacho y tipo</th>
                                        <th scope="col" class="px-4 py-3 text-left text-[10px] font-black uppercase tracking-widest text-gray-400">Revisión</th>
                                        <th scope="col" class="px-4 py-3 text-left text-[10px] font-black uppercase tracking-widest text-gray-400">Responsables</th>
                                        <th scope="col" class="sticky right-0 z-10 bg-gray-50 px-4 py-3 text-right text-[10px] font-black uppercase tracking-widest text-gray-400 dark:bg-gray-900">Acciones</th>
                                    </tr>
                                </thead>

                                <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-700 dark:bg-gray-800">
                                    <tr
                                        v-for="proceso in procesos.data"
                                        :key="proceso.id"
                                        class="group cursor-pointer transition-colors hover:bg-gray-50 dark:hover:bg-gray-900/35"
                                        :class="{ 'bg-indigo-50/50 dark:bg-indigo-900/10': proceso.is_pinned }"
                                        @click="openQuickView(proceso)"
                                    >
                                        <td class="relative px-4 py-4 align-top">
                                            <div v-if="proceso.is_pinned" class="absolute bottom-0 left-0 top-0 w-1 bg-indigo-500"></div>
                                            <div class="min-w-0 space-y-2">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <Link @click.stop :href="route('procesos.show', proceso.id)" class="break-all text-sm font-black leading-5 text-indigo-700 hover:underline dark:text-indigo-300">
                                                        {{ proceso.radicado || 'SIN RADICADO' }}
                                                    </Link>
                                                    <span v-if="proceso.is_pinned" class="rounded bg-indigo-100 px-1.5 py-0.5 text-[9px] font-black uppercase text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-200">Fijado</span>
                                                    <span v-if="proceso.es_spoa_nunc" class="rounded bg-indigo-100 px-1.5 py-0.5 text-[9px] font-black uppercase text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-200">SPOA/NUNC</span>
                                                    <button v-if="proceso.radicado" type="button" @click.stop="copyToClipboard(proceso.radicado)" class="rounded border border-gray-200 p-1 text-gray-400 hover:border-indigo-200 hover:text-indigo-600 dark:border-gray-700" title="Copiar radicado">
                                                        <DocumentDuplicateIcon class="h-3.5 w-3.5" />
                                                    </button>
                                                </div>
                                                <p class="line-clamp-2 max-w-md text-[11px] font-semibold leading-5 text-gray-500 dark:text-gray-400" :title="proceso.asunto">
                                                    {{ proceso.asunto || 'Sin asunto registrado' }}
                                                </p>
                                                <div class="flex items-center gap-2">
                                                    <div class="h-1.5 w-24 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                                                        <div class="h-full rounded-full" :class="integrityTone(proceso)" :style="integrityBarStyle(proceso)"></div>
                                                    </div>
                                                    <span class="text-[10px] font-black text-gray-500 dark:text-gray-400">{{ integrityScore(proceso) }}% integridad</span>
                                                    <span v-if="proceso.info_incompleta" class="rounded border border-rose-200 bg-rose-50 px-1.5 py-0.5 text-[9px] font-black uppercase text-rose-700 dark:border-rose-900/40 dark:bg-rose-900/10 dark:text-rose-300">Falta info</span>
                                                </div>
                                            </div>
                                        </td>

                                        <td class="px-4 py-4 align-top">
                                            <div class="max-w-xs space-y-2">
                                                <div class="flex items-start gap-2">
                                                    <span class="mt-0.5 shrink-0 rounded border border-blue-100 bg-blue-50 px-1.5 py-0.5 text-[9px] font-black text-blue-700">DTE</span>
                                                    <div class="min-w-0">
                                                        <p class="truncate text-[11px] font-bold text-gray-800 dark:text-gray-200" :title="formatNames(proceso.demandantes)">{{ primaryParty(proceso.demandantes) }}</p>
                                                        <p v-if="extraPartiesCount(proceso.demandantes)" class="text-[9px] font-bold uppercase text-gray-400">+{{ extraPartiesCount(proceso.demandantes) }} más</p>
                                                        <PersonaCompletenessIndicator :persona="firstIncompleteParty(proceso.demandantes)" compact class="mt-1" />
                                                    </div>
                                                </div>
                                                <div class="flex items-start gap-2">
                                                    <span class="mt-0.5 shrink-0 rounded border border-rose-100 bg-rose-50 px-1.5 py-0.5 text-[9px] font-black text-rose-700">DDO</span>
                                                    <div class="min-w-0">
                                                        <p class="truncate text-[11px] font-bold text-gray-800 dark:text-gray-200" :title="formatNames(proceso.demandados)">{{ primaryParty(proceso.demandados) }}</p>
                                                        <p v-if="extraPartiesCount(proceso.demandados)" class="text-[9px] font-bold uppercase text-gray-400">+{{ extraPartiesCount(proceso.demandados) }} más</p>
                                                        <PersonaCompletenessIndicator :persona="firstIncompleteParty(proceso.demandados)" compact class="mt-1" />
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        <td class="px-4 py-4 align-top">
                                            <div class="max-w-xs space-y-2">
                                                <p class="line-clamp-2 text-xs font-black text-gray-900 dark:text-white" :title="processOfficeName(proceso)">{{ processOfficeName(proceso) }}</p>
                                                <div class="flex flex-wrap gap-1.5">
                                                    <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-bold text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ processTypeName(proceso) }}</span>
                                                    <span v-if="proceso.clase_proceso" class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-bold text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ proceso.clase_proceso }}</span>
                                                </div>
                                                <div v-if="proceso.estado === 'CERRADO'" class="inline-flex rounded border border-emerald-200 bg-emerald-50 px-2 py-1 text-[9px] font-black uppercase text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-900/10 dark:text-emerald-300">Cerrado</div>
                                                <div v-else class="flex flex-col items-start gap-1.5">
                                                    <span class="rounded-lg border px-2.5 py-1 text-[10px] font-black uppercase leading-4 tracking-wider" :class="getEtapaColor(proceso.etapa_actual?.riesgo)">
                                                        {{ proceso.etapa_actual?.nombre || 'En trámite' }}
                                                    </span>
                                                    <span v-if="getVencimientoInfo(proceso)" class="inline-flex items-center gap-1 text-[9px] font-black uppercase tracking-wider" :class="getVencimientoInfo(proceso).class">
                                                        <ClockIcon class="h-3 w-3" />
                                                        {{ getVencimientoInfo(proceso).text }}
                                                    </span>
                                                </div>
                                            </div>
                                        </td>

                                        <td class="px-4 py-4 align-top">
                                            <div class="space-y-2">
                                                <div>
                                                    <p class="text-[9px] font-black uppercase tracking-widest text-gray-400">Última revisión</p>
                                                    <p class="text-[11px] font-bold text-gray-700 dark:text-gray-300">{{ formatDate(proceso.fecha_revision) || 'Sin registro' }}</p>
                                                </div>
                                                <div class="inline-flex max-w-full items-center gap-1.5 rounded-lg border px-2 py-1 text-[10px] font-black uppercase tracking-wider" :class="getRevisionStatus(proceso.fecha_proxima_revision)?.classes || 'border-gray-200 text-gray-500'">
                                                    <CalendarDaysIcon class="h-3.5 w-3.5 shrink-0" />
                                                    <span class="truncate">{{ formatDate(proceso.fecha_proxima_revision) || 'Pendiente' }}</span>
                                                </div>
                                                <span v-if="isInactiveProcess(proceso)" class="inline-flex rounded border border-rose-200 bg-rose-50 px-1.5 py-0.5 text-[9px] font-black uppercase text-rose-700 dark:border-rose-900/40 dark:bg-rose-900/10 dark:text-rose-300">
                                                    Inactivo {{ getInactivityDays(proceso.updated_at) }}d
                                                </span>
                                            </div>
                                        </td>

                                        <td class="px-4 py-4 align-top">
                                            <div class="space-y-2">
                                                <div class="flex items-center gap-2">
                                                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-[9px] font-black uppercase text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-200" :title="proceso.abogado?.name || 'Sin abogado'">
                                                        {{ userInitials(proceso.abogado?.name) }}
                                                    </div>
                                                    <div class="min-w-0">
                                                        <p class="text-[9px] font-black uppercase tracking-widest text-gray-400">Abogado</p>
                                                        <p class="truncate text-[11px] font-bold text-gray-700 dark:text-gray-300">{{ firstName(proceso.abogado?.name) }}</p>
                                                    </div>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-amber-50 text-[9px] font-black uppercase text-amber-700 dark:bg-amber-900/30 dark:text-amber-200" :title="proceso.responsable_revision?.name || 'Sin responsable'">
                                                        {{ userInitials(proceso.responsable_revision?.name) }}
                                                    </div>
                                                    <div class="min-w-0">
                                                        <p class="text-[9px] font-black uppercase tracking-widest text-gray-400">Revisión</p>
                                                        <p class="truncate text-[11px] font-bold text-gray-700 dark:text-gray-300">{{ firstName(proceso.responsable_revision?.name) }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        <td class="sticky right-0 z-10 bg-white px-4 py-4 text-right align-top shadow-[-4px_0_6px_-2px_rgba(0,0,0,0.05)] transition-colors group-hover:bg-gray-50 dark:bg-gray-800 dark:group-hover:bg-gray-900" :class="{ '!bg-indigo-50 dark:!bg-indigo-900/20': proceso.is_pinned }" @click.stop>
                                            <div class="flex items-center justify-end gap-2">
                                                <button
                                                    v-if="proceso.estado === 'ACTIVO'"
                                                    type="button"
                                                    @click.stop="quickReview(proceso)"
                                                    class="rounded-lg border border-emerald-200 bg-emerald-50 p-2 text-emerald-700 transition-colors hover:bg-emerald-600 hover:text-white dark:border-emerald-900/40 dark:bg-emerald-900/10 dark:text-emerald-300"
                                                    title="Marcar como revisado hoy"
                                                >
                                                    <HandThumbUpIcon class="h-4 w-4" />
                                                </button>
                                                <button
                                                    type="button"
                                                    @click.stop="togglePin(proceso)"
                                                    class="rounded-lg border p-2 transition-colors"
                                                    :class="proceso.is_pinned ? 'border-indigo-600 bg-indigo-600 text-white hover:bg-indigo-700' : 'border-gray-200 bg-gray-50 text-gray-500 hover:border-indigo-200 hover:text-indigo-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300'"
                                                    :title="proceso.is_pinned ? 'Desfijar' : 'Fijar al inicio'"
                                                >
                                                    <PinIcon class="h-4 w-4" :class="{ 'rotate-45': !proceso.is_pinned }" />
                                                </button>
                                                <Link @click.stop :href="route('procesos.show', proceso.id)" class="rounded-lg border border-indigo-200 bg-indigo-50 p-2 text-indigo-700 transition-colors hover:bg-indigo-600 hover:text-white dark:border-indigo-900/40 dark:bg-indigo-900/10 dark:text-indigo-300" title="Ver expediente">
                                                    <EyeIcon class="h-4 w-4" />
                                                </Link>

                                                <Dropdown align="right" width="48" teleport>
                                                    <template #trigger>
                                                        <button type="button" class="rounded-lg border border-gray-200 bg-white p-2 text-gray-500 transition-colors hover:border-indigo-200 hover:text-indigo-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                                            <EllipsisVerticalIcon class="h-4 w-4" />
                                                        </button>
                                                    </template>
                                                    <template #content>
                                                        <DropdownLink :href="route('procesos.edit', proceso.id)">
                                                            <div class="flex items-center gap-2"><PencilSquareIcon class="w-4 h-4" /> Editar información</div>
                                                        </DropdownLink>
                                                        <button v-if="proceso.estado === 'ACTIVO' && ['admin', 'abogado', 'gestor'].includes($page.props.auth.user.tipo_usuario)" type="button" @click="openCloseModal(proceso)" class="block w-full border-t px-4 py-2 text-left text-sm font-bold text-rose-700 hover:bg-rose-50 dark:border-gray-700 dark:hover:bg-rose-900/20">
                                                            <div class="flex items-center gap-2"><ArchiveBoxXMarkIcon class="w-4 h-4" /> Finalizar proceso</div>
                                                        </button>
                                                        <button v-if="proceso.estado === 'CERRADO' && $page.props.auth.user.tipo_usuario === 'admin'" type="button" @click="openReopenModal(proceso)" class="block w-full border-t px-4 py-2 text-left text-sm font-bold text-emerald-700 hover:bg-emerald-50 dark:border-gray-700 dark:hover:bg-emerald-900/20">
                                                            <div class="flex items-center gap-2"><ArrowPathIcon class="w-4 h-4" /> Reactivar caso</div>
                                                        </button>
                                                        <button v-if="$page.props.auth.user.tipo_usuario === 'admin'" type="button" @click="deleteProceso(proceso)" class="mt-1 block w-full border-t border-red-700 bg-red-600 px-4 py-3 text-left text-sm font-black uppercase tracking-widest text-white hover:bg-red-700">
                                                            <div class="flex items-center justify-center gap-2"><TrashIcon class="w-4 h-4" /> Eliminar registro</div>
                                                        </button>
                                                    </template>
                                                </Dropdown>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="divide-y divide-gray-100 dark:divide-gray-700 md:hidden">
                            <article
                                v-for="proceso in procesos.data"
                                :key="'mobile-' + proceso.id"
                                @click="openQuickView(proceso)"
                                class="cursor-pointer bg-white p-4 transition-colors hover:bg-gray-50 dark:bg-gray-800 dark:hover:bg-gray-900/40"
                                :class="{ 'border-l-4 border-indigo-500 bg-indigo-50/40 dark:bg-indigo-900/10': proceso.is_pinned }"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <Link @click.stop :href="route('procesos.show', proceso.id)" class="break-all text-base font-black leading-5 text-indigo-700 dark:text-indigo-300">
                                            {{ proceso.radicado || 'SIN RADICADO' }}
                                        </Link>
                                        <p class="mt-1 line-clamp-2 text-[11px] font-semibold leading-5 text-gray-500 dark:text-gray-400">{{ proceso.asunto || 'Sin asunto registrado' }}</p>
                                    </div>
                                    <button
                                        type="button"
                                        @click.stop="togglePin(proceso)"
                                        class="shrink-0 rounded-lg border p-2"
                                        :class="proceso.is_pinned ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-gray-200 bg-gray-50 text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300'"
                                        :title="proceso.is_pinned ? 'Desfijar' : 'Fijar al inicio'"
                                    >
                                        <PinIcon class="h-4 w-4" :class="{ 'rotate-45': !proceso.is_pinned }" />
                                    </button>
                                </div>

                                <div class="mt-3 flex flex-wrap gap-2">
                                    <span class="rounded-lg border px-2 py-1 text-[10px] font-black uppercase leading-4" :class="getEtapaColor(proceso.etapa_actual?.riesgo)">
                                        {{ proceso.etapa_actual?.nombre || proceso.estado || 'En trámite' }}
                                    </span>
                                    <span v-if="proceso.estado === 'CERRADO'" class="rounded border border-emerald-200 bg-emerald-50 px-2 py-1 text-[9px] font-black uppercase text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-900/10 dark:text-emerald-300">Cerrado</span>
                                    <span v-if="isInactiveProcess(proceso)" class="rounded border border-rose-200 bg-rose-50 px-2 py-1 text-[9px] font-black uppercase text-rose-700 dark:border-rose-900/40 dark:bg-rose-900/10 dark:text-rose-300">Inactivo {{ getInactivityDays(proceso.updated_at) }}d</span>
                                </div>

                                <dl class="mt-4 grid grid-cols-1 gap-3 text-xs sm:grid-cols-2">
                                    <div>
                                        <dt class="text-[9px] font-black uppercase tracking-widest text-gray-400">Demandante</dt>
                                        <dd class="mt-0.5 flex flex-wrap items-center gap-1.5 font-bold text-gray-800 dark:text-gray-200">
                                            <span>{{ primaryParty(proceso.demandantes) }}</span>
                                            <PersonaCompletenessIndicator :persona="firstIncompleteParty(proceso.demandantes)" compact />
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-[9px] font-black uppercase tracking-widest text-gray-400">Demandado</dt>
                                        <dd class="mt-0.5 flex flex-wrap items-center gap-1.5 font-bold text-gray-800 dark:text-gray-200">
                                            <span>{{ primaryParty(proceso.demandados) }}</span>
                                            <PersonaCompletenessIndicator :persona="firstIncompleteParty(proceso.demandados)" compact />
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-[9px] font-black uppercase tracking-widest text-gray-400">Despacho</dt>
                                        <dd class="mt-0.5 font-bold text-gray-800 dark:text-gray-200">{{ processOfficeName(proceso) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-[9px] font-black uppercase tracking-widest text-gray-400">Próxima revisión</dt>
                                        <dd class="mt-0.5 font-black text-gray-900 dark:text-white">{{ formatDate(proceso.fecha_proxima_revision) || 'Pendiente' }}</dd>
                                    </div>
                                </dl>

                                <div class="mt-4 flex items-center justify-between gap-3 border-t border-gray-100 pt-3 dark:border-gray-700">
                                    <div class="min-w-0">
                                        <p class="text-[9px] font-black uppercase tracking-widest text-gray-400">Responsable</p>
                                        <p class="text-[11px] font-bold text-gray-700 dark:text-gray-300">{{ firstName(proceso.abogado?.name) }} · {{ firstName(proceso.responsable_revision?.name) }}</p>
                                    </div>
                                    <div class="flex shrink-0 gap-2">
                                        <button v-if="proceso.estado === 'ACTIVO'" type="button" @click.stop="quickReview(proceso)" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-[10px] font-black uppercase text-emerald-700">Revisar</button>
                                        <Link @click.stop :href="route('procesos.show', proceso.id)" class="rounded-lg bg-indigo-600 px-3 py-2 text-[10px] font-black uppercase text-white">Ver</Link>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </template>

                    <div v-if="procesos.links.length > 3" class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 p-4 dark:border-gray-700">
                        <p class="text-xs font-bold text-gray-500 dark:text-gray-400">{{ resultRange }}</p>
                        <Pagination :links="procesos.links" />
                    </div>
                </section>
            </div>
        </div>

        <!-- MODALES REDISEÑADOS -->
        <Modal :show="showCloseModal" @close="showCloseModal = false" centered>
            <div class="p-8">
                <div class="flex items-center gap-3 mb-6">
                    <div class="p-2 bg-red-50 rounded-lg">
                        <ArchiveBoxXMarkIcon class="w-6 h-6 text-red-600" />
                    </div>
                    <h2 class="text-xl font-black text-gray-900">Cerrar Expediente</h2>
                </div>
                <p class="text-sm text-gray-500 mb-6 font-medium">Indica el motivo de cierre para el radicado <span class="font-mono font-bold text-red-600">{{ procesoToManage?.radicado }}</span>.</p>
                <div class="space-y-2">
                    <InputLabel value="Nota de Finalización" class="font-bold text-xs uppercase" />
                    <Textarea v-model="closeForm.nota_cierre" class="w-full rounded-xl border-gray-200" rows="4" placeholder="Ej: Sentencia favorable ejecutoriada..." />
                </div>
                <div class="mt-8 flex flex-col sm:flex-row justify-end gap-3">
                    <SecondaryButton @click="showCloseModal = false" class="!rounded-xl !px-6">Cancelar</SecondaryButton>
                    <DangerButton @click="submitCloseCase" class="!rounded-xl !px-8 !font-black shadow-lg shadow-red-100" :disabled="closeForm.processing">Confirmar Cierre</DangerButton>
                </div>
            </div>
        </Modal>

        <Modal :show="showReopenModal" @close="showReopenModal = false" centered>
            <div class="p-8">
                <div class="flex items-center gap-3 mb-6">
                    <div class="p-2 bg-green-50 rounded-lg">
                        <ArrowPathIcon class="w-6 h-6 text-green-600" />
                    </div>
                    <h2 class="text-xl font-black text-gray-900">Reabrir Expediente</h2>
                </div>
                <p class="text-sm text-gray-500 mb-8 font-medium">¿Estás seguro de que deseas reactivar este proceso judicial? Volverá al estado activo para su seguimiento.</p>
                <div class="flex flex-col sm:flex-row justify-end gap-3">
                    <SecondaryButton @click="showReopenModal = false" class="!rounded-xl !px-6">Cancelar</SecondaryButton>
                    <PrimaryButton @click="submitReopenCase" class="!bg-green-600 !rounded-xl !px-8 !font-black shadow-lg shadow-green-100" :disabled="reopenForm.processing">Sí, Reactivar</PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- MODAL DE VISTA RAPIDA -->
        <Modal :show="showQuickViewModal" @close="closeQuickView" max-width="5xl" centered>
            <div v-if="selectedProceso" class="flex max-h-[88vh] flex-col overflow-hidden rounded-lg border border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex shrink-0 flex-col gap-3 border-b border-gray-200 bg-gray-950 px-4 py-4 text-white dark:border-gray-700 sm:px-6 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-white/10 bg-white/10">
                            <ScaleIcon class="h-5 w-5" />
                        </div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Expediente #{{ selectedProceso.id }}</p>
                                <span class="rounded-md border px-2 py-0.5 text-[9px] font-black uppercase" :class="selectedProceso.estado === 'ACTIVO' ? 'border-emerald-400/40 bg-emerald-500/15 text-emerald-100' : 'border-gray-400/40 bg-gray-500/15 text-gray-100'">
                                    {{ selectedProceso.estado }}
                                </span>
                                <span v-if="selectedProceso.a_favor_de" class="rounded-md border border-white/10 bg-white/10 px-2 py-0.5 text-[9px] font-black uppercase text-gray-200">
                                    A favor {{ selectedProceso.a_favor_de }}
                                </span>
                                <span v-if="selectedProceso.es_spoa_nunc" class="rounded-md border border-white/10 bg-white/10 px-2 py-0.5 text-[9px] font-black uppercase text-gray-200">SPOA/NUNC</span>
                            </div>
                            <h2 class="mt-1 truncate font-mono text-lg font-black tracking-tight text-white">
                                {{ selectedProceso.radicado || 'SIN RADICADO' }}
                            </h2>
                            <p class="mt-0.5 truncate text-xs font-semibold text-gray-400">
                                {{ processOfficeName(selectedProceso) }} · {{ processTypeName(selectedProceso) }}
                            </p>
                        </div>
                    </div>
                    <div class="flex shrink-0 items-center justify-between gap-2 sm:justify-end">
                        <button @click="copyLegalInfo()" class="hidden items-center gap-2 rounded-lg border border-white/10 bg-white/10 px-3 py-2 text-[10px] font-black uppercase tracking-widest text-white transition hover:bg-white/15 sm:inline-flex">
                            <DocumentDuplicateIcon class="h-4 w-4" /> Copiar
                        </button>
                        <div class="flex items-center rounded-lg bg-white/10 p-0.5">
                            <button @click="prevProceso" :disabled="currentIndex === 0" class="rounded-md p-2 transition hover:bg-white/10 disabled:opacity-25" title="Anterior">
                                <ChevronDownIcon class="h-4 w-4 rotate-90" />
                            </button>
                            <span class="border-x border-white/10 px-3 text-[10px] font-black tracking-widest">{{ currentIndex + 1 }}/{{ procesos.data.length }}</span>
                            <button @click="nextProceso" :disabled="currentIndex === procesos.data.length - 1" class="rounded-md p-2 transition hover:bg-white/10 disabled:opacity-25" title="Siguiente">
                                <ChevronDownIcon class="h-4 w-4 -rotate-90" />
                            </button>
                        </div>
                        <button @click="closeQuickView" class="rounded-lg p-2 text-gray-300 transition hover:bg-white/10 hover:text-white" title="Cerrar">
                            <XMarkIcon class="h-5 w-5" />
                        </button>
                    </div>
                </div>

                <div class="grid min-h-0 flex-1 grid-cols-1 overflow-y-auto bg-gray-50 dark:bg-gray-950 lg:grid-cols-[20rem_minmax(0,1fr)]">
                    <aside class="space-y-4 border-b border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 lg:border-b-0 lg:border-r sm:p-5">
                        <section class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Radicado</p>
                            <div class="mt-2 flex min-w-0 items-center gap-2">
                                <p class="truncate font-mono text-sm font-black text-gray-950 dark:text-white">{{ selectedProceso.radicado || 'SIN ASIGNAR' }}</p>
                                <button v-if="selectedProceso.radicado" @click.stop="copyToClipboard(selectedProceso.radicado)" class="shrink-0 text-gray-400 hover:text-indigo-600"><DocumentDuplicateIcon class="h-4 w-4" /></button>
                            </div>
                            <div class="mt-4 grid grid-cols-2 gap-2">
                                <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800/60">
                                    <p class="text-[9px] font-black uppercase text-gray-400">Demandantes</p>
                                    <p class="mt-1 text-sm font-black text-gray-900 dark:text-white">{{ selectedProceso.demandantes?.length || 0 }}</p>
                                </div>
                                <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800/60">
                                    <p class="text-[9px] font-black uppercase text-gray-400">Demandados</p>
                                    <p class="mt-1 text-sm font-black text-gray-900 dark:text-white">{{ selectedProceso.demandados?.length || 0 }}</p>
                                </div>
                            </div>
                        </section>

                        <section class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Revisión</p>
                            <div class="mt-3 space-y-3">
                                <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800/60">
                                    <p class="text-[9px] font-black uppercase text-gray-400">Última revisión</p>
                                    <p class="mt-1 text-xs font-black text-gray-800 dark:text-gray-200">{{ formatDate(selectedProceso.fecha_revision) || 'Sin registro' }}</p>
                                </div>
                                <div class="rounded-lg border p-3" :class="getRevisionStatus(selectedProceso.fecha_proxima_revision)?.classes || 'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200'">
                                    <p class="text-[9px] font-black uppercase opacity-70">Próxima revisión</p>
                                    <p class="mt-1 text-xs font-black">{{ formatDate(selectedProceso.fecha_proxima_revision) || 'Pendiente programar' }}</p>
                                </div>
                            </div>
                        </section>

                        <section class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Responsables</p>
                            <div class="mt-3 space-y-3">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-xs font-black text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300">{{ userInitials(selectedProceso.abogado?.name) }}</div>
                                    <div class="min-w-0">
                                        <p class="text-[9px] font-black uppercase text-gray-400">Abogado</p>
                                        <p class="truncate text-xs font-black text-gray-800 dark:text-gray-200">{{ selectedProceso.abogado?.name || 'No asignado' }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-amber-50 text-xs font-black text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">{{ userInitials(selectedProceso.responsable_revision?.name) }}</div>
                                    <div class="min-w-0">
                                        <p class="text-[9px] font-black uppercase text-gray-400">Revisión</p>
                                        <p class="truncate text-xs font-black text-gray-800 dark:text-gray-200">{{ selectedProceso.responsable_revision?.name || 'No asignado' }}</p>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </aside>

                    <main class="min-w-0 space-y-4 p-4 sm:p-5">
                        <section class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                            <div class="rounded-lg border border-blue-100 bg-blue-50/50 p-4 dark:border-blue-900/50 dark:bg-blue-950/20 sm:p-5">
                                <div class="mb-3 flex items-center gap-2 text-blue-700 dark:text-blue-300">
                                    <UserGroupIcon class="h-4 w-4" />
                                    <h3 class="text-xs font-black uppercase tracking-widest">Demandantes</h3>
                                </div>
                                <div v-if="selectedProceso.demandantes?.length" class="space-y-2">
                                    <div v-for="p in selectedProceso.demandantes" :key="p.id" class="rounded-lg border border-blue-100 bg-white px-3 py-2 dark:border-blue-900/60 dark:bg-gray-900">
                                        <div class="flex flex-wrap items-start justify-between gap-2">
                                            <p class="min-w-0 text-xs font-black leading-5 text-gray-800 dark:text-gray-100">{{ p.nombre_completo }}</p>
                                            <PersonaCompletenessIndicator :persona="p" compact />
                                        </div>
                                        <p v-if="p.numero_documento" class="mt-0.5 text-[10px] font-bold text-gray-500 dark:text-gray-400">{{ p.tipo_documento || 'Doc.' }} {{ p.numero_documento }}</p>
                                    </div>
                                </div>
                                <p v-else class="text-xs font-semibold text-gray-400">No registrados</p>
                            </div>

                            <div class="rounded-lg border border-rose-100 bg-rose-50/50 p-4 dark:border-rose-900/50 dark:bg-rose-950/20 sm:p-5">
                                <div class="mb-3 flex items-center gap-2 text-rose-700 dark:text-rose-300">
                                    <UserGroupIcon class="h-4 w-4" />
                                    <h3 class="text-xs font-black uppercase tracking-widest">Demandados</h3>
                                </div>
                                <div v-if="selectedProceso.demandados?.length" class="space-y-2">
                                    <div v-for="p in selectedProceso.demandados" :key="p.id" class="rounded-lg border border-rose-100 bg-white px-3 py-2 dark:border-rose-900/60 dark:bg-gray-900">
                                        <div class="flex flex-wrap items-start justify-between gap-2">
                                            <p class="min-w-0 text-xs font-black leading-5 text-gray-800 dark:text-gray-100">{{ p.nombre_completo }}</p>
                                            <PersonaCompletenessIndicator :persona="p" compact />
                                        </div>
                                        <p v-if="p.numero_documento" class="mt-0.5 text-[10px] font-bold text-gray-500 dark:text-gray-400">{{ p.tipo_documento || 'Doc.' }} {{ p.numero_documento }}</p>
                                    </div>
                                </div>
                                <p v-else class="text-xs font-semibold text-gray-400">No registrados</p>
                            </div>
                        </section>

                        <section class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 sm:p-5">
                            <div class="mb-4 flex items-center gap-2">
                                <BuildingLibraryIcon class="h-4 w-4 text-indigo-500" />
                                <h3 class="text-xs font-black uppercase tracking-widest text-gray-900 dark:text-white">Despacho y asunto</h3>
                            </div>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div class="md:col-span-3 rounded-lg bg-indigo-50/60 p-4 dark:bg-indigo-950/20">
                                    <p class="text-[10px] font-black uppercase tracking-widest text-indigo-700 dark:text-indigo-300">Asunto</p>
                                    <p class="mt-1 text-sm font-bold leading-6 text-gray-800 dark:text-gray-100">{{ selectedProceso.asunto || 'Sin asunto registrado' }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Tipo de proceso</p>
                                    <p class="mt-1 text-xs font-black text-gray-800 dark:text-gray-200">{{ processTypeName(selectedProceso) }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Clase / subproceso</p>
                                    <p class="mt-1 text-xs font-black uppercase text-gray-800 dark:text-gray-200">{{ selectedProceso.clase_proceso || 'N/A' }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Etapa</p>
                                    <span class="mt-1 inline-flex rounded-lg border px-2.5 py-1 text-[10px] font-black uppercase" :class="getEtapaColor(selectedProceso.etapa_actual?.riesgo)">
                                        {{ selectedProceso.etapa_actual?.nombre || selectedProceso.estado || 'En trámite' }}
                                    </span>
                                </div>
                                <div class="md:col-span-3 rounded-lg bg-gray-50 p-3 dark:bg-gray-800/60">
                                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Despacho judicial</p>
                                    <p class="mt-1 flex items-start gap-2 text-sm font-bold leading-5 text-gray-800 dark:text-gray-200">
                                        <BuildingOfficeIcon class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                                        {{ processOfficeName(selectedProceso) }}
                                    </p>
                                </div>
                            </div>
                        </section>

                        <section class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Fecha radicado</p>
                                <p class="mt-1 text-xs font-black text-gray-800 dark:text-gray-200">{{ formatDate(selectedProceso.fecha_radicado) || 'Sin registro' }}</p>
                            </div>
                            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Vencimiento</p>
                                <p class="mt-1 text-xs font-black" :class="getVencimientoInfo(selectedProceso)?.class || 'text-gray-800 dark:text-gray-200'">{{ getVencimientoInfo(selectedProceso)?.text || 'Sin alerta' }}</p>
                            </div>
                            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Integridad</p>
                                <div class="mt-2 flex items-center gap-3">
                                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                                        <div class="h-full rounded-full" :class="integrityTone(selectedProceso)" :style="integrityBarStyle(selectedProceso)"></div>
                                    </div>
                                    <span class="text-xs font-black text-gray-700 dark:text-gray-200">{{ integrityScore(selectedProceso) }}%</span>
                                </div>
                            </div>
                        </section>

                        <section class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                                <div class="mb-3 flex items-center gap-2">
                                    <EnvelopeIcon class="h-4 w-4 text-gray-500" />
                                    <h3 class="text-xs font-black uppercase tracking-widest text-gray-900 dark:text-white">Comunicaciones</h3>
                                </div>
                                <div class="space-y-3">
                                    <div>
                                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Correo radicación</p>
                                        <p class="mt-1 truncate text-xs font-bold text-gray-700 dark:text-gray-300">{{ selectedProceso.correo_radicacion || 'No registrado' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Correos del juzgado</p>
                                        <p class="mt-1 text-xs font-bold leading-5 text-gray-700 dark:text-gray-300">{{ selectedProceso.correos_juzgado || 'No registrados' }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                                <div class="mb-3 flex items-center gap-2">
                                    <ClipboardDocumentListIcon class="h-4 w-4 text-gray-500" />
                                    <h3 class="text-xs font-black uppercase tracking-widest text-gray-900 dark:text-white">Observaciones y enlaces</h3>
                                </div>
                                <p class="text-xs font-semibold leading-5 text-gray-600 dark:text-gray-300">{{ selectedProceso.observaciones || 'Sin observaciones registradas.' }}</p>
                                <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    <a v-if="selectedProceso.link_expediente" :href="selectedProceso.link_expediente" target="_blank" class="inline-flex items-center justify-center gap-2 rounded-lg bg-gray-900 px-3 py-2 text-[10px] font-black uppercase tracking-widest text-white hover:bg-black dark:bg-white dark:text-gray-900">
                                        <LinkIcon class="h-4 w-4" /> Expediente
                                    </a>
                                    <a v-if="selectedProceso.ubicacion_drive" :href="selectedProceso.ubicacion_drive" target="_blank" class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-[10px] font-black uppercase tracking-widest text-white hover:bg-blue-700">
                                        <CloudArrowUpIcon class="h-4 w-4" /> Drive
                                    </a>
                                </div>
                            </div>
                        </section>
                    </main>
                </div>

                <div class="flex shrink-0 flex-col gap-3 border-t border-gray-200 bg-white px-4 py-4 dark:border-gray-700 dark:bg-gray-900 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <p class="text-[10px] font-bold text-gray-500 dark:text-gray-400">
                        Estado del expediente: <span class="font-black uppercase tracking-widest" :class="selectedProceso.estado === 'ACTIVO' ? 'text-emerald-600 dark:text-emerald-300' : 'text-gray-500'">{{ selectedProceso.estado }}</span>
                    </p>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <Link :href="route('procesos.edit', selectedProceso.id)" class="inline-flex justify-center rounded-lg border border-gray-200 px-4 py-2 text-[10px] font-black uppercase tracking-widest text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">
                            Editar
                        </Link>
                        <Link :href="route('procesos.show', selectedProceso.id)" class="inline-flex justify-center rounded-lg bg-gray-950 px-5 py-2 text-[10px] font-black uppercase tracking-widest text-white transition hover:bg-black dark:bg-white dark:text-gray-900">
                            Ingresar al expediente judicial &rarr;
                        </Link>
                    </div>
                </div>
            </div>
        </Modal>

    </AuthenticatedLayout>
</template>

<style scoped>
.custom-scrollbar-horizontal::-webkit-scrollbar { height: 6px; }
.custom-scrollbar-horizontal::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar-horizontal::-webkit-scrollbar-thumb { background-color: rgba(79, 70, 229, 0.1); border-radius: 20px; }
.sticky { position: -webkit-sticky; position: sticky; }
</style>
