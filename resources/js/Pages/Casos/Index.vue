<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import AsyncSelect from '@/Components/AsyncSelect.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import Textarea from '@/Components/Textarea.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PersonaCompletenessIndicator from '@/Components/PersonaCompletenessIndicator.vue';

// --- TIPOS DE ENTIDAD ---
const tiposEntidad = [
    'Juzgado', 'Fiscalía', 'Secretaría', 'Despacho', 'Centro de Servicios',
    'Corte', 'Tribunal', 'Notaría', 'Superintendencia'
];
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, watch, reactive, computed, onMounted } from 'vue';
import { TrashIcon, MagnifyingGlassIcon, InboxIcon, EyeIcon, ArrowDownTrayIcon, FunnelIcon, ArchiveBoxXMarkIcon, ChevronDownIcon, XMarkIcon, DocumentDuplicateIcon, CloudArrowUpIcon, BanknotesIcon, ExclamationCircleIcon, ArrowPathIcon, CheckCircleIcon, UserGroupIcon, ScaleIcon, PhoneIcon, EnvelopeIcon, BuildingOfficeIcon, ClockIcon, ExclamationTriangleIcon, UserIcon, ClipboardDocumentListIcon, LinkIcon, MapPinIcon as PinIcon, EllipsisVerticalIcon, PencilSquareIcon } from '@heroicons/vue/24/outline'; 
import { getCaseFinancialStatus } from '@/Utils/caseFinancialStatus';

const togglePin = (caso) => {
    router.patch(route('casos.pin', caso.id), {}, {
        preserveScroll: true,
    });
};
import { debounce } from 'lodash';
import AppAlert from '@/Utils/appAlert';

const props = defineProps({
    casos: Object,
    can: Object,
    filters: Object,
    abogados: Array,
    cooperativas: Array,
    juzgados: Array,
    selectedJuzgado: Object,
    etapas_procesales: Array,
    stats: Object,
});

// --- Lógica de Búsqueda y Filtros Combinada ---
const selectedJuzgado = ref(props.selectedJuzgado || null);
const filterStorageKey = 'casos.index.filters';
const filterKeys = ['search', 'abogado_id', 'cooperativa_id', 'juzgado_id', 'tipo_entidad', 'etapa_procesal', 'sin_radicado', 'inactivo_20_dias', 'cerrados', 'actualizados_hoy', 'integridad_baja'];
const booleanFilterKeys = ['sin_radicado', 'inactivo_20_dias', 'cerrados', 'actualizados_hoy', 'integridad_baja'];

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

const initialFilterValue = (key, fallback = '') => props.filters?.[key] ?? storedFilters[key] ?? fallback;

const hasUsefulFilterValue = (filters) => filterKeys.some((key) => {
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
        if (booleanFilterKeys.includes(key)) {
            return parseBooleanFilter(params.get(key));
        }

        return String(params.get(key) ?? '').trim() !== '';
    });
};

const filterForm = reactive({
    search: initialFilterValue('search'),
    abogado_id: initialFilterValue('abogado_id'),
    cooperativa_id: initialFilterValue('cooperativa_id'),
    juzgado_id: initialFilterValue('juzgado_id'),
    tipo_entidad: initialFilterValue('tipo_entidad'),
    etapa_procesal: initialFilterValue('etapa_procesal'),
    sin_radicado: parseBooleanFilter(initialFilterValue('sin_radicado', false)),
    inactivo_20_dias: parseBooleanFilter(initialFilterValue('inactivo_20_dias', false)),
    cerrados: parseBooleanFilter(initialFilterValue('cerrados', false)),
    actualizados_hoy: parseBooleanFilter(initialFilterValue('actualizados_hoy', false)),
    integridad_baja: parseBooleanFilter(initialFilterValue('integridad_baja', false)),
});

const controlFilterKeys = ['sin_radicado', 'inactivo_20_dias', 'cerrados', 'actualizados_hoy', 'integridad_baja'];

const currentFilterPayload = () => ({
    search: filterForm.search,
    abogado_id: filterForm.abogado_id,
    cooperativa_id: filterForm.cooperativa_id,
    juzgado_id: filterForm.juzgado_id,
    tipo_entidad: filterForm.tipo_entidad,
    etapa_procesal: filterForm.etapa_procesal,
    sin_radicado: filterForm.sin_radicado,
    inactivo_20_dias: filterForm.inactivo_20_dias,
    cerrados: filterForm.cerrados,
    actualizados_hoy: filterForm.actualizados_hoy,
    integridad_baja: filterForm.integridad_baja,
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
        router.get(route('casos.index'), storedFilters, {
            replace: true,
            preserveState: false,
        });
        return true;
    }

    return false;
};

const clearControlFilters = () => {
    controlFilterKeys.forEach(key => {
        filterForm[key] = false;
    });
};

const applyControlFilter = (filterKey) => {
    const nextValue = !filterForm[filterKey];
    clearControlFilters();
    filterForm[filterKey] = nextValue;
};

watch(selectedJuzgado, (val) => {
    filterForm.juzgado_id = val?.id || '';
});

const isDirty = computed(() => {
    return filterForm.search !== '' || 
           filterForm.abogado_id !== '' || 
           filterForm.cooperativa_id !== '' || 
           filterForm.juzgado_id !== '' || 
           filterForm.tipo_entidad !== '' || 
           filterForm.etapa_procesal !== '' ||
           filterForm.sin_radicado === true ||
           filterForm.inactivo_20_dias === true ||
           filterForm.cerrados === true ||
           filterForm.actualizados_hoy === true ||
           filterForm.integridad_baja === true;
});

const resetFilters = () => {
    if (typeof window !== 'undefined') {
        sessionStorage.removeItem(filterStorageKey);
    }

    filterForm.search = '';
    filterForm.abogado_id = '';
    filterForm.cooperativa_id = '';
    filterForm.juzgado_id = '';
    filterForm.tipo_entidad = '';
    selectedJuzgado.value = null;
    filterForm.etapa_procesal = '';
    filterForm.sin_radicado = false;
    filterForm.inactivo_20_dias = false;
    filterForm.cerrados = false;
    filterForm.actualizados_hoy = false;
    filterForm.integridad_baja = false;
};

watch(filterForm, debounce(() => {
    const filters = currentFilterPayload();
    persistFilters(filters);

    router.get(route('casos.index'), 
        filters, 
        {
            preserveState: true,
            replace: true,
        }
    );
}, 300));

// --- Lógica de Exportación ---
const exportarExcel = () => {
    window.location.href = route('casos.export', currentFilterPayload());
};

// --- Utilidades ---
const copyToClipboard = (text) => {
    if (!text) return;
    navigator.clipboard.writeText(text);
    // Podríamos añadir un toast aquí si existiera un sistema global
};

const getInactivityDays = (dateString) => {
    if (!dateString) return 0;
    const lastUpdate = new Date(dateString);
    const now = new Date();
    const diff = now - lastUpdate;
    return Math.floor(diff / (1000 * 60 * 60 * 24));
};

// --- Ayudantes de Estilo Dinámicos ---
const getEtapaColor = (etapa) => {
    if (!etapa) return 'bg-gray-100 text-gray-800 border-gray-200';
    
    const e = etapa.toLowerCase();
    
    if (e.includes('demanda presentada')) return 'bg-blue-100 text-blue-800 border-blue-200 dark:bg-blue-900/30 dark:text-blue-300';
    if (e.includes('sentencia')) return 'bg-purple-100 text-purple-800 border-purple-200 dark:bg-purple-900/30 dark:text-purple-300';
    if (e.includes('terminado') || e.includes('archivo')) return 'bg-green-100 text-green-800 border-green-200 dark:bg-green-900/30 dark:text-green-300';
    if (e.includes('notificaci')) return 'bg-indigo-100 text-indigo-800 border-indigo-200 dark:bg-indigo-900/30 dark:text-indigo-300';
    if (e.includes('medidas cautelares')) return 'bg-orange-100 text-orange-800 border-orange-200 dark:bg-orange-900/30 dark:text-orange-300';
    if (e.includes('rechazada') || e.includes('inadmitida')) return 'bg-red-100 text-red-800 border-red-200 dark:bg-red-900/30 dark:text-red-300';
    
    return 'bg-gray-100 text-gray-800 border-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600';
};

const formatTimeAgo = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    const now = new Date();
    const diffInSeconds = Math.floor((now - date) / 1000);
    
    if (diffInSeconds < 60) return `${diffInSeconds}s`;
    if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m`;
    if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h`;
    return `${Math.floor(diffInSeconds / 86400)}d`;
};

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    if (isNaN(date.getTime())) {
        return 'Fecha inválida';
    }
    return date.toLocaleDateString('es-CO', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
};

const formatLabel = (text) => {
    if (!text) return 'N/A';
    if (text.indexOf('_') === -1) {
        return text;
    }
    return text.replace(/_/g, ' ')
            .toLowerCase()
            .replace(/\b\w/g, char => char.toUpperCase());
};

const formatCurrency = (value) =>
    new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 })
    .format(value || 0);

const financialStatusFor = (caso) => getCaseFinancialStatus(caso);
const financialFieldFor = (caso, key) => financialStatusFor(caso).fields.find((field) => field.key === key);

const activeFilterCount = computed(() => filterKeys.reduce((count, key) => {
    if (booleanFilterKeys.includes(key)) {
        return count + (filterForm[key] ? 1 : 0);
    }

    return count + (String(filterForm[key] ?? '').trim() !== '' ? 1 : 0);
}, 0));

const resultRange = computed(() => {
    const total = props.casos?.total || 0;
    if (!total) return '0 resultados';

    const from = props.casos?.from || 1;
    const to = props.casos?.to || props.casos?.data?.length || total;
    return `${from}-${to} de ${total}`;
});

const summaryCards = computed(() => [
    {
        key: 'total',
        label: 'Total casos',
        value: props.stats?.total ?? '...',
        description: 'Expedientes visibles',
        icon: ArchiveBoxXMarkIcon,
        iconClass: 'text-slate-500',
        filterKey: null,
    },
    {
        key: 'sin_radicado',
        label: 'Sin radicado',
        value: props.stats?.sin_radicado ?? '...',
        description: 'Pendientes de radicar',
        icon: ExclamationCircleIcon,
        iconClass: 'text-amber-500',
        filterKey: 'sin_radicado',
        activeClass: 'ring-2 ring-amber-300 border-amber-200 bg-amber-50/70 dark:border-amber-500/60 dark:bg-amber-900/10',
    },
    {
        key: 'integridad_baja',
        label: 'Integridad baja',
        value: props.stats?.integridad_baja ?? '...',
        description: 'Expedientes incompletos',
        icon: ExclamationTriangleIcon,
        iconClass: 'text-rose-500',
        filterKey: 'integridad_baja',
        activeClass: 'ring-2 ring-rose-300 border-rose-200 bg-rose-50/70 dark:border-rose-500/60 dark:bg-rose-900/10',
    },
    {
        key: 'cerrados',
        label: 'Cerrados',
        value: props.stats?.cerrados ?? '...',
        description: 'Expedientes finalizados',
        icon: CheckCircleIcon,
        iconClass: 'text-emerald-500',
        filterKey: 'cerrados',
        activeClass: 'ring-2 ring-emerald-300 border-emerald-200 bg-emerald-50/70 dark:border-emerald-500/60 dark:bg-emerald-900/10',
    },
    {
        key: 'saldo_total',
        label: 'Saldo recuperable',
        value: formatCurrency(props.stats?.saldo_total || 0),
        description: 'Cartera pendiente',
        icon: BanknotesIcon,
        iconClass: 'text-teal-500',
        filterKey: null,
    },
    {
        key: 'actualizados_hoy',
        label: 'Actualizados hoy',
        value: props.stats?.actualizados_hoy ?? '...',
        description: 'Gestión reciente',
        icon: ArrowPathIcon,
        iconClass: 'text-indigo-500',
        filterKey: 'actualizados_hoy',
        activeClass: 'ring-2 ring-indigo-300 border-indigo-200 bg-indigo-50/70 dark:border-indigo-500/60 dark:bg-indigo-900/10',
    },
]);

const controlFilterItems = computed(() => [
    { key: 'sin_radicado', label: 'Sin radicado', count: props.stats?.sin_radicado, icon: ExclamationCircleIcon, activeClass: 'bg-amber-600 text-white border-amber-600' },
    { key: 'inactivo_20_dias', label: 'Inactivos +20d', count: null, icon: ClockIcon, activeClass: 'bg-orange-600 text-white border-orange-600' },
    { key: 'integridad_baja', label: 'Integridad baja', count: props.stats?.integridad_baja, icon: ExclamationTriangleIcon, activeClass: 'bg-rose-600 text-white border-rose-600' },
    { key: 'cerrados', label: 'Cerrados', count: props.stats?.cerrados, icon: CheckCircleIcon, activeClass: 'bg-emerald-600 text-white border-emerald-600' },
    { key: 'actualizados_hoy', label: 'Actualizados hoy', count: props.stats?.actualizados_hoy, icon: ArrowPathIcon, activeClass: 'bg-indigo-600 text-white border-indigo-600' },
]);

const userInitials = (name) => {
    if (!name) return 'SA';
    return name.split(' ').filter(Boolean).map(part => part[0]).join('').slice(0, 2).toUpperCase();
};

const responsibleNames = (caso) => caso.users?.length
    ? caso.users.map(user => user.name).filter(Boolean).join(', ')
    : 'Sin asignar';

const primaryResponsible = (caso) => caso.users?.[0]?.name || 'Sin asignar';

const integrityScore = (caso) => Number(caso.integridad_score || 0);
const integrityBarStyle = (caso) => ({ width: `${Math.min(Math.max(integrityScore(caso), 0), 100)}%` });
const integrityTone = (caso) => {
    const score = integrityScore(caso);
    if (score >= 85) return 'bg-emerald-500';
    if (score >= 60) return 'bg-amber-500';
    return 'bg-rose-500';
};

const isInactiveCase = (caso) => getInactivityDays(caso.updated_at) >= 30 && !caso.nota_cierre;

// --- Lógica de Eliminación ---
const confirmingCaseDeletion = ref(false);
const caseToDelete = ref(null);
const deleteForm = useForm({});

const confirmCaseDeletion = (caso) => {
    caseToDelete.value = caso;
    confirmingCaseDeletion.value = true;
};

const closeModal = () => {
    confirmingCaseDeletion.value = false;
    caseToDelete.value = null;
};

const deleteCase = () => {
    deleteForm.delete(route('casos.destroy', caseToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            closeModal();
            // Opcional: Toast o alerta de éxito
        },
        onFinish: () => deleteForm.reset(),
    });
};

// --- Lógica de Quick View Modal ---
const selectedCaso = ref(null);
const showQuickViewModal = ref(false);

const openQuickView = (caso) => {
    selectedCaso.value = caso;
    showQuickViewModal.value = true;
};

const closeQuickView = () => {
    showQuickViewModal.value = false;
    setTimeout(() => { selectedCaso.value = null; }, 300);
};

watch(() => props.casos.data, (casos) => {
    if (!selectedCaso.value) return;

    const refreshed = casos.find(c => c.id === selectedCaso.value.id);
    if (refreshed) {
        selectedCaso.value = refreshed;
        return;
    }

    closeQuickView();
});

// --- MODALES DE GESTIÓN ---
const casoToManage = ref(null);
const showCloseModal = ref(false);
const closeForm = useForm({ nota_cierre: '' });

const openCloseModal = (caso) => {
    casoToManage.value = caso;
    closeForm.reset();
    showCloseModal.value = true;
};

const submitCloseCase = () => {
    closeForm.patch(route('casos.close', casoToManage.value.id), {
        preserveScroll: true,
        onSuccess: () => { showCloseModal.value = false; casoToManage.value = null; }
    });
};

const currentIndex = computed(() => {
    if (!selectedCaso.value) return -1;
    return props.casos.data.findIndex(c => c.id === selectedCaso.value.id);
});

const nextCaso = () => {
    const idx = currentIndex.value;
    if (idx < props.casos.data.length - 1) {
        selectedCaso.value = props.casos.data[idx + 1];
    } else if (props.casos.next_page_url) {
        router.get(props.casos.next_page_url, {}, {
            preserveScroll: true,
            onSuccess: () => {
                const params = new URLSearchParams(window.location.search);
                params.set('autonext', 'first');
                window.history.replaceState({}, '', `${window.location.pathname}?${params.toString()}`);
            }
        });
    }
};

const prevCaso = () => {
    const idx = currentIndex.value;
    if (idx > 0) {
        selectedCaso.value = props.casos.data[idx - 1];
    } else if (props.casos.prev_page_url) {
        router.get(props.casos.prev_page_url, {}, {
            preserveScroll: true,
            onSuccess: () => {
                const params = new URLSearchParams(window.location.search);
                params.set('autonext', 'last');
                window.history.replaceState({}, '', `${window.location.pathname}?${params.toString()}`);
            }
        });
    }
};

onMounted(() => {
    if (reapplyStoredFiltersIfNeeded()) return;

    const params = new URLSearchParams(window.location.search);
    const autonext = params.get('autonext');
    if (autonext && props.casos.data.length > 0) {
        if (autonext === 'first') {
            openQuickView(props.casos.data[0]);
        } else if (autonext === 'last') {
            openQuickView(props.casos.data[props.casos.data.length - 1]);
        }
        params.delete('autonext');
        const newRelativePathQuery = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
        window.history.replaceState({}, '', newRelativePathQuery);
    }
});

const caseStatus = computed(() => {
    if (!selectedCaso.value) return null;
    const c = selectedCaso.value;
    const financialStatus = financialStatusFor(c);
    return {
        hasRadicado: !!c.radicado,
        hasJuzgado: !!c.juzgado,
        hasAbogado: c.users && c.users.length > 0,
        hasFinancial: !financialStatus.hasMissing,
        financialMissing: financialStatus.missingLabels,
        isCompleted: !!c.radicado && !!c.juzgado && (c.users && c.users.length > 0)
    };
});

const copyLegalInfo = (caso) => {
    const c = caso || selectedCaso.value;
    if (!c) return;
    
    const radicado = c.radicado || 'SIN RADICADO';
    const juzgado = c.juzgado?.nombre || 'POR DEFINIR';
    const deudor = c.deudor?.nombre_completo || 'SIN DEUDOR';
    const cooperativa = c.cooperativa?.nombre || 'N/A';
    
    let text = `EXPEDIENTE LEGAL\n`;
    text += `-----------------\n`;
    text += `Radicado: ${radicado}\n`;
    text += `Juzgado: ${juzgado}\n`;
    text += `Deudor: ${deudor}\n`;
    text += `Cooperativa: ${cooperativa}\n`;
    
    if (c.codeudores?.length > 0) {
        text += `Codeudores: ${c.codeudores.map(cd => cd.nombre_completo).join(', ')}\n`;
    }
    
    navigator.clipboard.writeText(text).then(() => {
        AppAlert.fire({
            title: '¡Copiado!',
            text: 'Datos listos para pegar.',
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
    <Head title="Gestión de Casos" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="mx-auto max-w-[1600px] space-y-4 px-4 sm:px-6 lg:px-8">
                
                <!-- Encabezado y Acciones Principales -->
                <section class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 sm:p-5 shadow-sm">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div class="min-w-0">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-300">
                                    <ArchiveBoxXMarkIcon class="h-6 w-6" />
                                </div>
                                <div class="min-w-0">
                                    <h2 id="tour-casos-title" class="text-xl font-black tracking-tight text-gray-950 dark:text-white sm:text-2xl">
                                        Gestión de Casos
                                    </h2>
                                    <p class="mt-1 text-sm font-medium text-gray-500 dark:text-gray-400">
                                        Seguimiento jurídico, estado financiero y responsables en una sola vista.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="grid w-full grid-cols-1 gap-2 sm:grid-cols-3 lg:w-auto">
                            <Link
                                id="tour-btn-importar"
                                :href="route('casos.import.show')"
                                class="inline-flex items-center justify-center gap-2 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-2.5 text-xs font-black uppercase tracking-widest text-indigo-700 shadow-sm transition-colors hover:border-indigo-300 hover:bg-indigo-100 dark:border-indigo-900/60 dark:bg-indigo-950/30 dark:text-indigo-300"
                            >
                                <CloudArrowUpIcon class="h-4 w-4" />
                                Carga asistida
                            </Link>
                            <button
                                id="tour-btn-exportar"
                                type="button"
                                @click="exportarExcel"
                                class="inline-flex items-center justify-center gap-2 rounded-lg border border-emerald-600 bg-emerald-600 px-4 py-2.5 text-xs font-black uppercase tracking-widest text-white shadow-sm transition-colors hover:bg-emerald-700"
                            >
                                <ArrowDownTrayIcon class="h-4 w-4" />
                                Exportar
                            </button>
                            <Link
                                :href="route('casos.create')"
                                class="inline-flex items-center justify-center gap-2 rounded-lg border border-indigo-600 bg-indigo-600 px-4 py-2.5 text-xs font-black uppercase tracking-widest text-white shadow-sm transition-colors hover:bg-indigo-700"
                            >
                                <PlusIcon class="h-4 w-4" />
                                Registrar
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
                            :class="[filterForm[card.filterKey] ? card.activeClass : '']"
                            class="group rounded-lg border border-gray-200 bg-white p-4 text-left shadow-sm transition-all hover:border-indigo-200 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-indigo-300 dark:border-gray-700 dark:bg-gray-800"
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
                        <div
                            v-else
                            class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                        >
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
                                <h3 class="text-sm font-black uppercase tracking-tight text-gray-950 dark:text-white">Filtros de búsqueda</h3>
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
                        <div class="lg:col-span-5">
                            <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-gray-400">Búsqueda general</label>
                            <div class="relative">
                                <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                                <input
                                    v-model="filterForm.search"
                                    type="text"
                                    class="block w-full rounded-lg border border-gray-200 bg-gray-50 py-2.5 pl-9 pr-3 text-sm font-semibold text-gray-800 transition-colors focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                    placeholder="Nombre, cédula, radicado, pagaré, cooperativa o juzgado"
                                />
                            </div>
                        </div>

                        <div class="lg:col-span-3">
                            <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-gray-400">Cooperativa</label>
                            <SelectInput v-model="filterForm.cooperativa_id" class="w-full rounded-lg py-2.5 text-sm">
                                <option value="">Todas</option>
                                <option v-for="c in cooperativas" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                            </SelectInput>
                        </div>

                        <div class="lg:col-span-2">
                            <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-gray-400">Responsable</label>
                            <SelectInput v-model="filterForm.abogado_id" class="w-full rounded-lg py-2.5 text-sm">
                                <option value="">Todos</option>
                                <option v-for="a in abogados" :key="a.id" :value="a.id">{{ a.name }}</option>
                            </SelectInput>
                        </div>

                        <div class="lg:col-span-2">
                            <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-gray-400">Etapa</label>
                            <SelectInput v-model="filterForm.etapa_procesal" class="w-full rounded-lg py-2.5 text-sm">
                                <option value="">Todas</option>
                                <option v-for="e in etapas_procesales" :key="e" :value="e">{{ e }}</option>
                            </SelectInput>
                        </div>

                        <div class="lg:col-span-3">
                            <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-gray-400">Tipo de entidad</label>
                            <SelectInput v-model="filterForm.tipo_entidad" class="w-full rounded-lg py-2.5 text-sm">
                                <option value="">Todas</option>
                                <option v-for="tipo in tiposEntidad" :key="tipo" :value="tipo">{{ tipo }}</option>
                            </SelectInput>
                        </div>

                        <div class="lg:col-span-5">
                            <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-gray-400">Despacho o juzgado</label>
                            <AsyncSelect
                                v-model="selectedJuzgado"
                                :endpoint="route('juzgados.search')"
                                placeholder="Buscar juzgado o despacho"
                                label-key="nombre"
                                class="!min-h-[42px] !rounded-lg"
                            />
                        </div>

                        <div class="lg:col-span-4">
                            <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-gray-400">Control rápido</label>
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-3">
                                <button
                                    v-for="item in controlFilterItems"
                                    :key="item.key"
                                    type="button"
                                    @click="applyControlFilter(item.key)"
                                    :class="filterForm[item.key] ? item.activeClass : 'border-gray-200 bg-gray-50 text-gray-600 hover:border-indigo-200 hover:text-indigo-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300'"
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

                <!-- Listado de Casos -->
                <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex flex-col gap-3 border-b border-gray-100 p-4 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-sm font-black uppercase tracking-tight text-gray-950 dark:text-white">Listado de expedientes</h3>
                            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400">{{ resultRange }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">
                            <span class="rounded-lg border border-gray-200 px-2.5 py-1.5 dark:border-gray-700">Click para vista rápida</span>
                            <span v-if="isDirty" class="rounded-lg bg-indigo-50 px-2.5 py-1.5 text-indigo-700 dark:bg-indigo-900/20 dark:text-indigo-300">Filtrado</span>
                        </div>
                    </div>

                    <div v-if="casos.data.length === 0" class="flex min-h-72 flex-col items-center justify-center p-8 text-center">
                        <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-lg bg-gray-50 dark:bg-gray-900">
                            <InboxIcon class="h-8 w-8 text-gray-400" />
                        </div>
                        <h3 class="text-base font-black text-gray-950 dark:text-white">No se encontraron casos</h3>
                        <p class="mt-1 max-w-sm text-sm font-medium text-gray-500 dark:text-gray-400">
                            No hay registros que coincidan con los filtros aplicados.
                        </p>
                        <button v-if="isDirty" type="button" @click="resetFilters" class="mt-4 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-2 text-xs font-black uppercase tracking-widest text-indigo-700 hover:bg-indigo-100">
                            Limpiar filtros
                        </button>
                    </div>

                    <template v-else>
                        <div class="hidden overflow-x-auto custom-scrollbar-horizontal md:block">
                            <table class="min-w-[1180px] divide-y divide-gray-100 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-900/40">
                                    <tr>
                                        <th scope="col" class="px-4 py-3 text-left text-[10px] font-black uppercase tracking-widest text-gray-400">Expediente</th>
                                        <th scope="col" class="px-4 py-3 text-left text-[10px] font-black uppercase tracking-widest text-gray-400">Entidad y despacho</th>
                                        <th scope="col" class="px-4 py-3 text-left text-[10px] font-black uppercase tracking-widest text-gray-400">Estado procesal</th>
                                        <th scope="col" class="px-4 py-3 text-left text-[10px] font-black uppercase tracking-widest text-gray-400">Responsable y actividad</th>
                                        <th scope="col" class="px-4 py-3 text-left text-[10px] font-black uppercase tracking-widest text-gray-400">Finanzas</th>
                                        <th scope="col" class="sticky right-0 z-10 bg-gray-50 px-4 py-3 text-right text-[10px] font-black uppercase tracking-widest text-gray-400 dark:bg-gray-900">Acciones</th>
                                    </tr>
                                </thead>

                                <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-700 dark:bg-gray-800">
                                    <tr
                                        v-for="caso in casos.data"
                                        :key="caso.id"
                                        class="group cursor-pointer transition-colors hover:bg-gray-50 dark:hover:bg-gray-900/35"
                                        :class="{ 'bg-indigo-50/50 dark:bg-indigo-900/10': caso.is_pinned }"
                                        @click="openQuickView(caso)"
                                    >
                                        <td class="relative px-4 py-4 align-top">
                                            <div v-if="caso.is_pinned" class="absolute bottom-0 left-0 top-0 w-1 bg-indigo-500"></div>
                                            <div class="min-w-0 space-y-2">
                                                <div class="flex items-start gap-2">
                                                    <Link @click.stop :href="route('casos.show', caso.id)" class="break-words text-sm font-black leading-5 text-indigo-700 hover:underline dark:text-indigo-300">
                                                        {{ caso.deudor?.nombre_completo ?? 'Sin deudor' }}
                                                    </Link>
                                                    <span v-if="caso.is_pinned" class="shrink-0 rounded bg-indigo-100 px-1.5 py-0.5 text-[9px] font-black uppercase text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-200">Fijado</span>
                                                    <PersonaCompletenessIndicator :persona="caso.deudor" compact />
                                                </div>
                                                <div class="flex flex-wrap items-center gap-1.5">
                                                    <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-black text-gray-600 dark:bg-gray-700 dark:text-gray-300">ID #{{ caso.id }}</span>
                                                    <button
                                                        v-if="caso.radicado"
                                                        type="button"
                                                        @click.stop="copyToClipboard(caso.radicado)"
                                                        class="inline-flex items-center gap-1 rounded border border-gray-200 px-1.5 py-0.5 text-[10px] font-bold text-gray-600 hover:border-indigo-200 hover:text-indigo-600 dark:border-gray-700 dark:text-gray-300"
                                                        title="Copiar radicado"
                                                    >
                                                        Rad. {{ caso.radicado }}
                                                        <DocumentDuplicateIcon class="h-3 w-3" />
                                                    </button>
                                                    <span v-else class="rounded border border-amber-200 bg-amber-50 px-1.5 py-0.5 text-[10px] font-black text-amber-700 dark:border-amber-900/40 dark:bg-amber-900/10 dark:text-amber-300">Sin radicado</span>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <div class="h-1.5 w-24 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                                                        <div class="h-full rounded-full" :class="integrityTone(caso)" :style="integrityBarStyle(caso)"></div>
                                                    </div>
                                                    <span class="text-[10px] font-black text-gray-500 dark:text-gray-400">{{ integrityScore(caso) }}% integridad</span>
                                                    <span
                                                        v-if="financialStatusFor(caso).hasMissing"
                                                        class="rounded border border-amber-200 bg-amber-50 px-1.5 py-0.5 text-[9px] font-black uppercase text-amber-700 dark:border-amber-900/40 dark:bg-amber-900/10 dark:text-amber-300"
                                                        :title="financialStatusFor(caso).detail"
                                                    >
                                                        Finanzas por completar
                                                    </span>
                                                </div>
                                            </div>
                                        </td>

                                        <td class="px-4 py-4 align-top">
                                            <div class="max-w-xs space-y-2">
                                                <p class="text-xs font-black text-gray-900 dark:text-white">{{ caso.cooperativa?.nombre ?? 'N/A' }}</p>
                                                <p class="line-clamp-2 text-[11px] font-semibold leading-5 text-gray-500 dark:text-gray-400">
                                                    {{ caso.juzgado?.nombre || 'Despacho por definir' }}
                                                </p>
                                                <div class="flex flex-wrap gap-1.5">
                                                    <span v-if="caso.referencia_credito" class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-bold text-gray-600 dark:bg-gray-700 dark:text-gray-300">Pagaré {{ caso.referencia_credito }}</span>
                                                    <span v-if="caso.tipo_proceso" class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-bold text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ caso.tipo_proceso }}</span>
                                                </div>
                                            </div>
                                        </td>

                                        <td class="px-4 py-4 align-top">
                                            <div class="flex max-w-xs flex-col items-start gap-2">
                                                <span class="rounded-lg border px-2.5 py-1 text-[10px] font-black uppercase leading-4 tracking-wider" :class="getEtapaColor(caso.etapa_procesal || caso.estado_proceso)">
                                                    {{ caso.etapa_procesal || caso.estado_proceso || 'Sin etapa' }}
                                                </span>
                                                <span v-if="caso.nota_cierre" class="rounded border border-emerald-200 bg-emerald-50 px-2 py-1 text-[9px] font-black uppercase text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-900/10 dark:text-emerald-300">Cerrado</span>
                                                <span v-if="caso.es_spoa_nunc" class="rounded border border-indigo-200 bg-indigo-50 px-2 py-1 text-[9px] font-black uppercase text-indigo-700 dark:border-indigo-900/40 dark:bg-indigo-900/10 dark:text-indigo-300">SPOA/NUNC</span>
                                            </div>
                                        </td>

                                        <td class="px-4 py-4 align-top">
                                            <div class="space-y-3">
                                                <div class="flex items-center gap-2">
                                                    <div class="flex -space-x-2">
                                                        <template v-if="caso.users && caso.users.length > 0">
                                                            <div
                                                                v-for="u in caso.users.slice(0, 3)"
                                                                :key="u.id"
                                                                class="flex h-7 w-7 items-center justify-center rounded-full border-2 border-white bg-indigo-50 text-[9px] font-black uppercase text-indigo-700 dark:border-gray-800 dark:bg-indigo-900/40 dark:text-indigo-200"
                                                                :title="u.name"
                                                            >
                                                                {{ userInitials(u.name) }}
                                                            </div>
                                                            <div v-if="caso.users.length > 3" class="flex h-7 w-7 items-center justify-center rounded-full border-2 border-white bg-gray-100 text-[9px] font-black text-gray-600 dark:border-gray-800 dark:bg-gray-700 dark:text-gray-300">+{{ caso.users.length - 3 }}</div>
                                                        </template>
                                                        <div v-else class="flex h-7 w-7 items-center justify-center rounded-full bg-gray-100 text-[9px] font-black text-gray-500 dark:bg-gray-700 dark:text-gray-300">SA</div>
                                                    </div>
                                                    <p class="line-clamp-2 text-[11px] font-bold leading-4 text-gray-700 dark:text-gray-300" :title="responsibleNames(caso)">
                                                        {{ primaryResponsible(caso) }}
                                                    </p>
                                                </div>
                                                <div>
                                                    <p class="text-[11px] font-black text-gray-700 dark:text-gray-300">{{ formatDate(caso.updated_at) }}</p>
                                                    <p class="text-[10px] font-semibold text-gray-400">Hace {{ formatTimeAgo(caso.updated_at) }}</p>
                                                    <span v-if="isInactiveCase(caso)" class="mt-1 inline-flex rounded border border-rose-200 bg-rose-50 px-1.5 py-0.5 text-[9px] font-black uppercase text-rose-700 dark:border-rose-900/40 dark:bg-rose-900/10 dark:text-rose-300">
                                                        Inactivo {{ getInactivityDays(caso.updated_at) }}d
                                                    </span>
                                                </div>
                                            </div>
                                        </td>

                                        <td class="px-4 py-4 align-top">
                                            <div class="space-y-2">
                                                <div>
                                                    <p class="text-[9px] font-black uppercase tracking-widest text-gray-400">Saldo actual</p>
                                                    <p v-if="financialFieldFor(caso, 'monto_deuda_actual')?.missing" class="text-xs font-black uppercase text-amber-700 dark:text-amber-300">Por registrar</p>
                                                    <p v-else class="text-sm font-black text-gray-900 dark:text-white">{{ formatCurrency(caso.monto_deuda_actual) }}</p>
                                                </div>
                                                <div class="flex flex-wrap gap-1.5">
                                                    <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-bold text-gray-600 dark:bg-gray-700 dark:text-gray-300">Base {{ financialFieldFor(caso, 'monto_total')?.missing ? 'N/A' : formatCurrency(caso.monto_total) }}</span>
                                                    <span class="rounded bg-emerald-50 px-1.5 py-0.5 text-[10px] font-bold text-emerald-700 dark:bg-emerald-900/10 dark:text-emerald-300">Pagado {{ financialFieldFor(caso, 'monto_total_pagado')?.missing ? 'N/A' : formatCurrency(caso.monto_total_pagado) }}</span>
                                                </div>
                                            </div>
                                        </td>

                                        <td class="sticky right-0 z-10 bg-white px-4 py-4 text-right align-top shadow-[-4px_0_6px_-2px_rgba(0,0,0,0.05)] transition-colors group-hover:bg-gray-50 dark:bg-gray-800 dark:group-hover:bg-gray-900" :class="{ '!bg-indigo-50 dark:!bg-indigo-900/20': caso.is_pinned }" @click.stop>
                                            <div class="flex items-center justify-end gap-2">
                                                <button
                                                    v-if="!caso.nota_cierre && ['admin', 'abogado', 'gestor'].includes($page.props.auth.user.tipo_usuario)"
                                                    type="button"
                                                    @click.stop="openCloseModal(caso)"
                                                    class="rounded-lg border border-amber-200 bg-amber-50 p-2 text-amber-700 transition-colors hover:bg-amber-600 hover:text-white dark:border-amber-900/40 dark:bg-amber-900/10 dark:text-amber-300"
                                                    title="Finalizar/Cerrar proceso"
                                                >
                                                    <ArchiveBoxXMarkIcon class="h-4 w-4" />
                                                </button>

                                                <button
                                                    type="button"
                                                    @click.stop="togglePin(caso)"
                                                    class="rounded-lg border p-2 transition-colors"
                                                    :class="caso.is_pinned ? 'border-indigo-600 bg-indigo-600 text-white hover:bg-indigo-700' : 'border-gray-200 bg-gray-50 text-gray-500 hover:border-indigo-200 hover:text-indigo-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300'"
                                                    :title="caso.is_pinned ? 'Desfijar' : 'Fijar al inicio'"
                                                >
                                                    <PinIcon class="h-4 w-4" :class="{ 'rotate-45': !caso.is_pinned }" />
                                                </button>

                                                <Link
                                                    :href="route('casos.show', caso.id)"
                                                    class="rounded-lg border border-indigo-200 bg-indigo-50 p-2 text-indigo-700 transition-colors hover:bg-indigo-600 hover:text-white dark:border-indigo-900/40 dark:bg-indigo-900/10 dark:text-indigo-300"
                                                    title="Ver expediente"
                                                >
                                                    <EyeIcon class="h-4 w-4" />
                                                </Link>

                                                <Dropdown align="right" width="48" teleport>
                                                    <template #trigger>
                                                        <button type="button" class="rounded-lg border border-gray-200 bg-white p-2 text-gray-500 transition-colors hover:border-indigo-200 hover:text-indigo-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                                            <EllipsisVerticalIcon class="h-4 w-4" />
                                                        </button>
                                                    </template>
                                                    <template #content>
                                                        <DropdownLink :href="route('casos.edit', caso.id)">
                                                            <div class="flex items-center gap-2"><PencilSquareIcon class="w-4 h-4" /> Editar caso</div>
                                                        </DropdownLink>
                                                        <button
                                                            v-if="!caso.nota_cierre && ['admin', 'abogado', 'gestor'].includes($page.props.auth.user.tipo_usuario)"
                                                            type="button"
                                                            @click="openCloseModal(caso)"
                                                            class="block w-full border-t px-4 py-2 text-left text-sm font-bold text-amber-700 hover:bg-amber-50 dark:border-gray-700 dark:hover:bg-amber-900/20"
                                                        >
                                                            <div class="flex items-center gap-2"><ArchiveBoxXMarkIcon class="w-4 h-4" /> Finalizar proceso</div>
                                                        </button>
                                                        <button
                                                            v-if="$page.props.auth.user.tipo_usuario === 'admin'"
                                                            type="button"
                                                            @click="confirmCaseDeletion(caso)"
                                                            class="mt-1 block w-full border-t border-red-700 bg-red-600 px-4 py-3 text-left text-sm font-black uppercase tracking-widest text-white hover:bg-red-700"
                                                        >
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
                                v-for="caso in casos.data"
                                :key="'mobile-' + caso.id"
                                @click="openQuickView(caso)"
                                class="cursor-pointer bg-white p-4 transition-colors hover:bg-gray-50 dark:bg-gray-800 dark:hover:bg-gray-900/40"
                                :class="{ 'border-l-4 border-indigo-500 bg-indigo-50/40 dark:bg-indigo-900/10': caso.is_pinned }"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-start gap-2">
                                            <Link @click.stop :href="route('casos.show', caso.id)" class="break-words text-sm font-black leading-5 text-indigo-700 dark:text-indigo-300">
                                                {{ caso.deudor?.nombre_completo ?? 'Sin deudor' }}
                                            </Link>
                                            <PersonaCompletenessIndicator :persona="caso.deudor" compact />
                                        </div>
                                        <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                            <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-black text-gray-600 dark:bg-gray-700 dark:text-gray-300">ID #{{ caso.id }}</span>
                                            <span v-if="caso.radicado" class="rounded border border-gray-200 px-1.5 py-0.5 text-[10px] font-bold text-gray-600 dark:border-gray-700 dark:text-gray-300">Rad. {{ caso.radicado }}</span>
                                            <span v-else class="rounded border border-amber-200 bg-amber-50 px-1.5 py-0.5 text-[10px] font-black text-amber-700 dark:border-amber-900/40 dark:bg-amber-900/10 dark:text-amber-300">Sin radicado</span>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        @click.stop="togglePin(caso)"
                                        class="shrink-0 rounded-lg border p-2"
                                        :class="caso.is_pinned ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-gray-200 bg-gray-50 text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300'"
                                        :title="caso.is_pinned ? 'Desfijar' : 'Fijar al inicio'"
                                    >
                                        <PinIcon class="h-4 w-4" :class="{ 'rotate-45': !caso.is_pinned }" />
                                    </button>
                                </div>

                                <div class="mt-3 flex flex-wrap gap-2">
                                    <span class="rounded-lg border px-2 py-1 text-[10px] font-black uppercase leading-4" :class="getEtapaColor(caso.etapa_procesal || caso.estado_proceso)">
                                        {{ caso.etapa_procesal || caso.estado_proceso || 'Sin etapa' }}
                                    </span>
                                    <span v-if="caso.nota_cierre" class="rounded border border-emerald-200 bg-emerald-50 px-2 py-1 text-[9px] font-black uppercase text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-900/10 dark:text-emerald-300">Cerrado</span>
                                    <span v-if="isInactiveCase(caso)" class="rounded border border-rose-200 bg-rose-50 px-2 py-1 text-[9px] font-black uppercase text-rose-700 dark:border-rose-900/40 dark:bg-rose-900/10 dark:text-rose-300">Inactivo {{ getInactivityDays(caso.updated_at) }}d</span>
                                </div>

                                <dl class="mt-4 grid grid-cols-1 gap-3 text-xs sm:grid-cols-2">
                                    <div>
                                        <dt class="text-[9px] font-black uppercase tracking-widest text-gray-400">Cooperativa</dt>
                                        <dd class="mt-0.5 font-bold text-gray-800 dark:text-gray-200">{{ caso.cooperativa?.nombre ?? 'N/A' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-[9px] font-black uppercase tracking-widest text-gray-400">Responsable</dt>
                                        <dd class="mt-0.5 font-bold text-gray-800 dark:text-gray-200">{{ primaryResponsible(caso) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-[9px] font-black uppercase tracking-widest text-gray-400">Despacho</dt>
                                        <dd class="mt-0.5 font-bold text-gray-800 dark:text-gray-200">{{ caso.juzgado?.nombre || 'Por definir' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-[9px] font-black uppercase tracking-widest text-gray-400">Saldo actual</dt>
                                        <dd v-if="financialFieldFor(caso, 'monto_deuda_actual')?.missing" class="mt-0.5 font-black uppercase text-amber-700 dark:text-amber-300">Por registrar</dd>
                                        <dd v-else class="mt-0.5 font-black text-gray-900 dark:text-white">{{ formatCurrency(caso.monto_deuda_actual) }}</dd>
                                    </div>
                                </dl>

                                <div class="mt-4 flex items-center justify-between gap-3 border-t border-gray-100 pt-3 dark:border-gray-700">
                                    <div class="min-w-0">
                                        <p class="text-[9px] font-black uppercase tracking-widest text-gray-400">Actualizado</p>
                                        <p class="text-[11px] font-bold text-gray-700 dark:text-gray-300">{{ formatDate(caso.updated_at) }} · hace {{ formatTimeAgo(caso.updated_at) }}</p>
                                    </div>
                                    <div class="flex shrink-0 gap-2">
                                        <Link @click.stop :href="route('casos.edit', caso.id)" class="rounded-lg border border-gray-200 px-3 py-2 text-[10px] font-black uppercase text-gray-600 dark:border-gray-700 dark:text-gray-300">Editar</Link>
                                        <Link @click.stop :href="route('casos.show', caso.id)" class="rounded-lg bg-indigo-600 px-3 py-2 text-[10px] font-black uppercase text-white">Ver</Link>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </template>

                    <div v-if="casos && casos.links?.length > 3" class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 p-4 dark:border-gray-700">
                        <p class="text-xs font-bold text-gray-500 dark:text-gray-400">{{ resultRange }}</p>
                        <div class="flex flex-wrap justify-end gap-2">
                            <Link
                                v-for="(link, idx) in casos.links"
                                :key="idx"
                                :href="link.url || '#'"
                                v-html="link.label"
                                class="rounded-lg border px-3 py-2 text-sm font-bold dark:border-gray-600"
                                :class="{
                                    'pointer-events-none bg-gray-100 text-gray-400 dark:bg-gray-700 dark:text-gray-500': !link.url,
                                    'border-indigo-600 bg-indigo-600 text-white': link.active,
                                    'hover:bg-gray-50 dark:hover:bg-gray-700': link.url && !link.active
                                }"
                            />
                        </div>
                    </div>
                </section>

            </div>
        </div>

        <Modal :show="confirmingCaseDeletion" @close="closeModal">
            <div class="p-6">
                <div class="flex items-center gap-3 mb-4 text-amber-600">
                    <ExclamationTriangleIcon class="w-8 h-8" />
                    <h2 class="text-xl font-black uppercase tracking-tight">
                        ¿Mover caso a Papelera?
                    </h2>
                </div>

                <div class="p-4 bg-amber-50 border border-amber-100 rounded-xl mb-6">
                    <p class="text-[11px] text-amber-700 font-black uppercase tracking-widest mb-2 italic underline">⚠️ Por favor lea con atención:</p>
                    <ul class="text-[11px] text-amber-800 space-y-1.5 list-disc pl-5 font-bold leading-tight">
                        <li>El caso dejará de aparecer en la lista de activos.</li>
                        <li>Toda la información (actuaciones, documentos y pagos) <span class="text-indigo-600 underline">SE CONSERVA</span>.</li>
                        <li>Podrás consultarlo y recuperarlo desde el filtro de "Estado: Cerrados/Papelera".</li>
                        <li>Use esta opción para corregir errores de registro o casos archivados.</li>
                    </ul>
                </div>

                <div v-if="caseToDelete" class="mt-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700">
                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Expediente Seleccionado:</p>
                    <p class="text-sm font-black text-gray-900 dark:text-white uppercase truncate">
                        {{ caseToDelete.deudor?.nombre_completo || 'Sin nombre' }}
                    </p>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="text-[10px] font-black px-1.5 py-0.5 bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-400 rounded uppercase">ID #{{ caseToDelete.id }}</span>
                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-tighter">Rad: {{ caseToDelete.radicado || 'Sin radicar' }}</span>
                    </div>
                </div>

                <div class="mt-8 flex justify-end gap-3">
                    <SecondaryButton @click="closeModal" class="uppercase text-[10px] font-black tracking-widest px-6 py-3">
                        No, cancelar
                    </SecondaryButton>

                    <PrimaryButton
                        @click="deleteCase"
                        :class="{ 'opacity-25': deleteForm.processing }"
                        :disabled="deleteForm.processing"
                        class="uppercase text-[10px] font-black tracking-widest px-6 py-3 bg-indigo-600"
                    >
                        Sí, mover a papelera
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- MODAL DE VISTA RAPIDA -->
        <Modal :show="showQuickViewModal" @close="closeQuickView" max-width="5xl" centered>
            <div v-if="selectedCaso" class="flex max-h-[88vh] flex-col overflow-hidden rounded-lg border border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex shrink-0 flex-col gap-3 border-b border-gray-200 bg-gray-950 px-4 py-4 text-white dark:border-gray-700 sm:px-6 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-white/10 bg-white/10">
                            <ArchiveBoxXMarkIcon class="h-5 w-5" />
                        </div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Caso #{{ selectedCaso.id }}</p>
                                <span class="rounded-md border px-2 py-0.5 text-[9px] font-black uppercase" :class="selectedCaso.nota_cierre ? 'border-rose-400/40 bg-rose-500/15 text-rose-100' : 'border-emerald-400/40 bg-emerald-500/15 text-emerald-100'">
                                    {{ selectedCaso.nota_cierre ? 'Cerrado' : 'Activo' }}
                                </span>
                                <span class="rounded-md border border-white/10 bg-white/10 px-2 py-0.5 text-[9px] font-black uppercase text-gray-200">
                                    {{ integrityScore(selectedCaso) }}% integridad
                                </span>
                            </div>
                            <div class="mt-1 flex min-w-0 flex-wrap items-center gap-2">
                                <h2 class="truncate text-lg font-black tracking-tight text-white">
                                    {{ selectedCaso.deudor?.nombre_completo || 'Sin deudor registrado' }}
                                </h2>
                                <PersonaCompletenessIndicator :persona="selectedCaso.deudor" compact />
                            </div>
                            <p class="mt-0.5 truncate text-xs font-semibold text-gray-400">
                                {{ selectedCaso.cooperativa?.nombre || 'Sin empresa' }} · {{ selectedCaso.radicado || 'Sin radicado' }}
                            </p>
                        </div>
                    </div>
                    <div class="flex shrink-0 items-center justify-between gap-2 sm:justify-end">
                        <button @click="copyLegalInfo()" class="hidden items-center gap-2 rounded-lg border border-white/10 bg-white/10 px-3 py-2 text-[10px] font-black uppercase tracking-widest text-white transition hover:bg-white/15 sm:inline-flex">
                            <DocumentDuplicateIcon class="h-4 w-4" /> Copiar
                        </button>
                        <div class="flex items-center rounded-lg bg-white/10 p-0.5">
                            <button @click="prevCaso" :disabled="currentIndex === 0" class="rounded-md p-2 transition hover:bg-white/10 disabled:opacity-25" title="Anterior">
                                <ChevronDownIcon class="h-4 w-4 rotate-90" />
                            </button>
                            <span class="border-x border-white/10 px-3 text-[10px] font-black tracking-widest">{{ currentIndex + 1 }}/{{ casos.data.length }}</span>
                            <button @click="nextCaso" :disabled="currentIndex === casos.data.length - 1" class="rounded-md p-2 transition hover:bg-white/10 disabled:opacity-25" title="Siguiente">
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
                            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Titular principal</p>
                            <p class="mt-2 text-sm font-black leading-5 text-gray-950 dark:text-white">{{ selectedCaso.deudor?.nombre_completo || 'No especificado' }}</p>
                            <PersonaCompletenessIndicator :persona="selectedCaso.deudor" class="mt-2" />
                            <p class="mt-1 text-xs font-bold text-gray-500 dark:text-gray-400">{{ selectedCaso.deudor?.numero_documento || 'Sin documento' }}</p>
                            <div class="mt-4 space-y-2">
                                <p class="flex items-center gap-2 text-xs font-bold text-gray-700 dark:text-gray-300">
                                    <PhoneIcon class="h-4 w-4 text-gray-400" /> {{ selectedCaso.deudor?.celular_1 || 'Teléfono no registrado' }}
                                </p>
                                <p class="flex min-w-0 items-center gap-2 text-xs font-bold text-gray-700 dark:text-gray-300">
                                    <EnvelopeIcon class="h-4 w-4 shrink-0 text-gray-400" />
                                    <span class="truncate">{{ selectedCaso.deudor?.correo_1 || 'Correo no registrado' }}</span>
                                </p>
                            </div>
                        </section>

                        <section class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Responsables</p>
                                <span class="rounded-md bg-gray-100 px-2 py-1 text-[10px] font-black text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ selectedCaso.users?.length || 0 }}</span>
                            </div>
                            <div class="space-y-2">
                                <div v-for="u in selectedCaso.users" :key="u.id" class="flex items-center gap-2 rounded-lg bg-indigo-50 px-3 py-2 text-xs font-black text-indigo-700 dark:bg-indigo-950/30 dark:text-indigo-300">
                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-white text-[10px] dark:bg-gray-900">{{ userInitials(u.name) }}</span>
                                    <span class="truncate">{{ u.name }}</span>
                                </div>
                                <p v-if="!selectedCaso.users?.length" class="text-xs font-semibold text-gray-400">Sin abogados asignados</p>
                            </div>
                        </section>

                        <section class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                            <div class="mb-2 flex items-center justify-between gap-3">
                                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Integridad</p>
                                <span class="text-xs font-black text-gray-700 dark:text-gray-200">{{ integrityScore(selectedCaso) }}%</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                                <div class="h-full rounded-full" :class="integrityTone(selectedCaso)" :style="integrityBarStyle(selectedCaso)"></div>
                            </div>
                            <p class="mt-3 text-xs font-semibold text-gray-500 dark:text-gray-400">{{ responsibleNames(selectedCaso) }}</p>
                        </section>
                    </aside>

                    <main class="min-w-0 space-y-4 p-4 sm:p-5">
                        <section class="grid grid-cols-1 gap-3 md:grid-cols-3">
                            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Radicado</p>
                                <div class="mt-2 flex min-w-0 items-center gap-2">
                                    <p class="truncate font-mono text-sm font-black text-gray-950 dark:text-white">{{ selectedCaso.radicado || 'SIN ASIGNAR' }}</p>
                                    <button v-if="selectedCaso.radicado" @click.stop="copyToClipboard(selectedCaso.radicado)" class="shrink-0 text-gray-400 hover:text-indigo-600"><DocumentDuplicateIcon class="h-4 w-4" /></button>
                                </div>
                                <span v-if="selectedCaso.es_spoa_nunc" class="mt-2 inline-flex rounded-md bg-indigo-50 px-2 py-1 text-[9px] font-black uppercase text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300">SPOA/NUNC</span>
                            </div>
                            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Pagaré</p>
                                <div class="mt-2 flex min-w-0 items-center gap-2">
                                    <p class="truncate text-sm font-black text-gray-950 dark:text-white">{{ selectedCaso.referencia_credito || 'SIN ASIGNAR' }}</p>
                                    <button v-if="selectedCaso.referencia_credito" @click.stop="copyToClipboard(selectedCaso.referencia_credito)" class="shrink-0 text-gray-400 hover:text-indigo-600"><DocumentDuplicateIcon class="h-4 w-4" /></button>
                                </div>
                            </div>
                            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Etapa</p>
                                <p class="mt-2 text-sm font-black uppercase text-indigo-700 dark:text-indigo-300">{{ selectedCaso.etapa_procesal || selectedCaso.estado_proceso || 'N/A' }}</p>
                            </div>
                        </section>

                        <section class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_20rem]">
                            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 sm:p-5">
                                <div class="mb-4 flex items-center gap-2">
                                    <ScaleIcon class="h-4 w-4 text-indigo-500" />
                                    <h3 class="text-xs font-black uppercase tracking-widest text-gray-900 dark:text-white">Proceso judicial</h3>
                                </div>
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div class="sm:col-span-2 rounded-lg bg-gray-50 p-3 dark:bg-gray-800/60">
                                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Juzgado</p>
                                        <p class="mt-1 text-sm font-bold leading-5 text-gray-800 dark:text-gray-200">{{ selectedCaso.juzgado?.nombre || 'Por definir despacho' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Especialidad</p>
                                        <p class="mt-1 text-xs font-black uppercase text-gray-800 dark:text-gray-200">{{ selectedCaso.especialidad?.nombre || 'Civil' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Tipo proceso</p>
                                        <p class="mt-1 text-xs font-black text-gray-800 dark:text-gray-200">{{ selectedCaso.tipo_proceso || 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Subproceso</p>
                                        <p class="mt-1 text-xs font-black text-gray-800 dark:text-gray-200">{{ selectedCaso.subproceso || 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Etapa actual</p>
                                        <p class="mt-1 text-xs font-black text-gray-800 dark:text-gray-200">{{ selectedCaso.etapa_actual || 'N/A' }}</p>
                                    </div>
                                    <div class="rounded-lg border border-amber-100 bg-amber-50 p-3 dark:border-amber-900/50 dark:bg-amber-950/20">
                                        <p class="text-[10px] font-black uppercase tracking-widest text-amber-700 dark:text-amber-300">Garantía</p>
                                        <p class="mt-1 text-xs font-black text-amber-900 dark:text-amber-200">{{ selectedCaso.tipo_garantia_asociada || 'Sin garantía registrada' }}</p>
                                    </div>
                                    <div class="rounded-lg border border-amber-100 bg-amber-50 p-3 dark:border-amber-900/50 dark:bg-amber-950/20">
                                        <p class="text-[10px] font-black uppercase tracking-widest text-amber-700 dark:text-amber-300">Origen documental</p>
                                        <p class="mt-1 text-xs font-black uppercase text-amber-900 dark:text-amber-200">{{ selectedCaso.origen_documental || 'N/A' }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 sm:p-5">
                                <div class="mb-4 flex items-center gap-2">
                                    <BanknotesIcon class="h-4 w-4 text-teal-500" />
                                    <h3 class="text-xs font-black uppercase tracking-widest text-gray-900 dark:text-white">Cartera</h3>
                                </div>
                                <div v-if="financialStatusFor(selectedCaso).hasMissing" class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-900/60 dark:bg-amber-950/20">
                                    <p class="text-[10px] font-black uppercase tracking-widest text-amber-700 dark:text-amber-300">Montos por completar</p>
                                    <p class="mt-1 text-xs font-semibold text-amber-700 dark:text-amber-300">{{ financialStatusFor(selectedCaso).detail }}</p>
                                </div>
                                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Deuda actual</p>
                                <p v-if="financialFieldFor(selectedCaso, 'monto_deuda_actual')?.missing" class="mt-1 text-sm font-black uppercase text-amber-700 dark:text-amber-300">Pendiente</p>
                                <p v-else class="mt-1 text-2xl font-black text-gray-950 dark:text-white">{{ formatCurrency(selectedCaso.monto_deuda_actual) }}</p>
                                <div class="mt-5 grid grid-cols-1 gap-3">
                                    <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800/60">
                                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Obligación base</p>
                                        <p v-if="financialFieldFor(selectedCaso, 'monto_total')?.missing" class="mt-1 text-xs font-black uppercase text-amber-700 dark:text-amber-300">Por registrar</p>
                                        <p v-else class="mt-1 text-sm font-black text-gray-800 dark:text-gray-200">{{ formatCurrency(selectedCaso.monto_total) }}</p>
                                    </div>
                                    <div class="rounded-lg bg-emerald-50 p-3 dark:bg-emerald-950/20">
                                        <p class="text-[10px] font-black uppercase tracking-widest text-emerald-700 dark:text-emerald-300">Total pagado</p>
                                        <p v-if="financialFieldFor(selectedCaso, 'monto_total_pagado')?.missing" class="mt-1 text-xs font-black uppercase text-amber-700 dark:text-amber-300">Por registrar</p>
                                        <p v-else-if="financialFieldFor(selectedCaso, 'monto_total_pagado')?.displayLabel" class="mt-1 text-xs font-black uppercase text-emerald-700 dark:text-emerald-300">{{ financialFieldFor(selectedCaso, 'monto_total_pagado').displayLabel }}</p>
                                        <p v-else class="mt-1 text-sm font-black text-emerald-700 dark:text-emerald-300">{{ formatCurrency(selectedCaso.monto_total_pagado) }}</p>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Apertura</p>
                                <p class="mt-1 text-xs font-black text-gray-800 dark:text-gray-200">{{ formatDate(selectedCaso.fecha_apertura) }}</p>
                            </div>
                            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Vencimiento</p>
                                <p class="mt-1 text-xs font-black text-gray-800 dark:text-gray-200">{{ formatDate(selectedCaso.fecha_vencimiento) }}</p>
                            </div>
                            <div class="rounded-lg border border-rose-100 bg-rose-50 p-4 dark:border-rose-900/50 dark:bg-rose-950/20">
                                <p class="text-[10px] font-black uppercase tracking-widest text-rose-600 dark:text-rose-300">Mora</p>
                                <p class="mt-1 text-xs font-black text-rose-700 dark:text-rose-300">{{ selectedCaso.dias_en_mora || 0 }} días</p>
                            </div>
                        </section>

                        <section class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                                <div class="mb-3 flex items-center gap-2">
                                    <UserGroupIcon class="h-4 w-4 text-gray-500" />
                                    <h3 class="text-xs font-black uppercase tracking-widest text-gray-900 dark:text-white">Codeudores</h3>
                                </div>
                                <div v-if="selectedCaso.codeudores?.length" class="flex flex-wrap gap-2">
                                    <div v-for="c in selectedCaso.codeudores" :key="c.id" class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-[10px] font-black uppercase text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                        <span>{{ c.nombre_completo }}</span>
                                        <PersonaCompletenessIndicator :persona="c" :href="route('casos.edit', selectedCaso.id) + '?tab=codeudores'" compact class="mt-1" />
                                    </div>
                                </div>
                                <p v-else class="text-xs font-semibold text-gray-400">Sin codeudores registrados</p>
                            </div>
                            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                                <div class="mb-3 flex items-center gap-2">
                                    <ClipboardDocumentListIcon class="h-4 w-4 text-gray-500" />
                                    <h3 class="text-xs font-black uppercase tracking-widest text-gray-900 dark:text-white">Notas y enlaces</h3>
                                </div>
                                <p class="text-xs font-semibold leading-5 text-gray-600 dark:text-gray-300">{{ selectedCaso.notas_legales || 'Sin observaciones de proceso registradas.' }}</p>
                                <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    <a v-if="selectedCaso.link_drive" :href="selectedCaso.link_drive" target="_blank" class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-[10px] font-black uppercase tracking-widest text-white hover:bg-blue-700">
                                        <LinkIcon class="h-4 w-4" /> Drive
                                    </a>
                                    <a v-if="selectedCaso.link_expediente" :href="selectedCaso.link_expediente" target="_blank" class="inline-flex items-center justify-center gap-2 rounded-lg bg-gray-900 px-3 py-2 text-[10px] font-black uppercase tracking-widest text-white hover:bg-black dark:bg-white dark:text-gray-900">
                                        <CloudArrowUpIcon class="h-4 w-4" /> Expediente
                                    </a>
                                </div>
                            </div>
                        </section>
                    </main>
                </div>

                <div class="flex shrink-0 flex-col gap-3 border-t border-gray-200 bg-white px-4 py-4 dark:border-gray-700 dark:bg-gray-900 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <p class="text-[10px] font-bold text-gray-500 dark:text-gray-400">
                        Actualizado {{ formatDate(selectedCaso.updated_at) }} · hace {{ formatTimeAgo(selectedCaso.updated_at) }}
                    </p>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <Link :href="route('casos.edit', selectedCaso.id)" class="inline-flex justify-center rounded-lg border border-gray-200 px-4 py-2 text-[10px] font-black uppercase tracking-widest text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">
                            Editar
                        </Link>
                        <Link :href="route('casos.show', selectedCaso.id)" class="inline-flex justify-center rounded-lg bg-gray-950 px-5 py-2 text-[10px] font-black uppercase tracking-widest text-white transition hover:bg-black dark:bg-white dark:text-gray-900">
                            Ver expediente completo &rarr;
                        </Link>
                    </div>
                </div>
            </div>
        </Modal>

        <Modal :show="showCloseModal" @close="showCloseModal = false" centered>
            <div class="p-8">
                <div class="flex items-center gap-3 mb-6">
                    <div class="p-2 bg-red-50 rounded-lg">
                        <ArchiveBoxXMarkIcon class="w-6 h-6 text-red-600" />
                    </div>
                    <h2 class="text-xl font-black text-gray-900">Finalizar / Cerrar Expediente</h2>
                </div>
                <p class="text-sm text-gray-500 mb-6 font-medium">Indica el motivo de cierre para el caso de <span class="font-bold text-red-600">{{ casoToManage?.deudor?.nombre_completo }}</span>.</p>
                <div class="space-y-2">
                    <InputLabel value="Nota de Finalización" class="font-bold text-xs uppercase" />
                    <Textarea v-model="closeForm.nota_cierre" class="w-full rounded-xl border-gray-200" rows="4" placeholder="Ej: Pago total recibido, acuerdo conciliatorio, desistimiento..." />
                </div>
                <div class="mt-8 flex flex-col sm:flex-row justify-end gap-3">
                    <SecondaryButton @click="showCloseModal = false" class="!rounded-xl !px-6">Cancelar</SecondaryButton>
                    <DangerButton @click="submitCloseCase" class="!rounded-xl !px-8 !font-black shadow-lg shadow-red-100" :disabled="closeForm.processing">Confirmar Cierre</DangerButton>
                </div>
            </div>
        </Modal>

    </AuthenticatedLayout>
</template>

<style scoped>
/* Elimina el scroll del contenedor padre si el hijo ya tiene scroll */
.custom-scrollbar-horizontal {
    scrollbar-width: thin;
    scrollbar-color: rgba(156, 163, 175, 0.5) transparent;
}

.custom-scrollbar-horizontal::-webkit-scrollbar {
    height: 6px;
}

.custom-scrollbar-horizontal::-webkit-scrollbar-track {
    background: transparent;
}

.custom-scrollbar-horizontal::-webkit-scrollbar-thumb {
    background-color: rgba(156, 163, 175, 0.5);
    border-radius: 20px;
}

/* Soporte para sticky en navegadores que lo requieran */
.sticky {
    position: -webkit-sticky;
    position: sticky;
}

/* Sombra sutil para la columna fija */
.sticky.right-0 {
    box-shadow: -4px 0 6px -2px rgba(0, 0, 0, 0.05);
}

.dark .sticky.right-0 {
    box-shadow: -4px 0 6px -2px rgba(0, 0, 0, 0.3);
}
</style>
