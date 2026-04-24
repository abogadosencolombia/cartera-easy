<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import AsyncSelect from '@/Components/AsyncSelect.vue';
import Dropdown from '@/Components/Dropdown.vue';

// --- TIPOS DE ENTIDAD ---
const tiposEntidad = [
    'Juzgado', 'Fiscalía', 'Secretaría', 'Despacho', 'Centro de Servicios',
    'Corte', 'Tribunal', 'Notaría', 'Superintendencia'
];
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, watch, reactive, computed, onMounted } from 'vue';
import { TrashIcon, MagnifyingGlassIcon, InboxIcon, EyeIcon, ArrowDownTrayIcon, FunnelIcon, ArchiveBoxXMarkIcon, ChevronDownIcon, XMarkIcon, DocumentDuplicateIcon, CloudArrowUpIcon, BanknotesIcon, ExclamationCircleIcon, ArrowPathIcon, CheckCircleIcon, UserGroupIcon, ScaleIcon, PhoneIcon, EnvelopeIcon, BuildingOfficeIcon, ClockIcon, ExclamationTriangleIcon, UserIcon, ClipboardDocumentListIcon, LinkIcon, MapPinIcon as PinIcon } from '@heroicons/vue/24/outline'; 

const togglePin = (caso) => {
    router.patch(route('casos.pin', caso.id), {}, {
        preserveScroll: true,
    });
};
import { debounce } from 'lodash';
import Swal from 'sweetalert2';

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

const filterForm = reactive({
    search: props.filters?.search ?? '',
    abogado_id: props.filters?.abogado_id ?? '',
    cooperativa_id: props.filters?.cooperativa_id ?? '',
    juzgado_id: props.filters?.juzgado_id ?? '',
    tipo_entidad: props.filters?.tipo_entidad ?? '',
    etapa_procesal: props.filters?.etapa_procesal ?? '',
    sin_radicado: props.filters?.sin_radicado === 'true' || props.filters?.sin_radicado === true,
});

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
           filterForm.sin_radicado === true;
});

const resetFilters = () => {
    filterForm.search = '';
    filterForm.abogado_id = '';
    filterForm.cooperativa_id = '';
    filterForm.juzgado_id = '';
    filterForm.tipo_entidad = '';
    selectedJuzgado.value = null;
    filterForm.etapa_procesal = '';
    filterForm.sin_radicado = false;
};

watch(filterForm, debounce(() => {
    router.get(route('casos.index'), 
        { 
            search: filterForm.search, 
            abogado_id: filterForm.abogado_id,
            cooperativa_id: filterForm.cooperativa_id,
            juzgado_id: filterForm.juzgado_id,
            tipo_entidad: filterForm.tipo_entidad,
            etapa_procesal: filterForm.etapa_procesal,
            sin_radicado: filterForm.sin_radicado,
        }, 
        {
            preserveState: true,
            replace: true,
        }
    );
}, 300));

// --- Lógica de Exportación ---
const exportarExcel = () => {
    window.location.href = route('casos.export', { 
        search: filterForm.search,
        abogado_id: filterForm.abogado_id,
        cooperativa_id: filterForm.cooperativa_id,
        juzgado_id: filterForm.juzgado_id,
        tipo_entidad: filterForm.tipo_entidad,
        etapa_procesal: filterForm.etapa_procesal,
        sin_radicado: filterForm.sin_radicado,
    });
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
    return {
        hasRadicado: !!c.radicado,
        hasJuzgado: !!c.juzgado,
        hasAbogado: c.users && c.users.length > 0,
        hasFinancial: (c.monto_total || 0) > 0,
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
        Swal.fire({
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
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
                
                <!-- Encabezado y Acciones Principales -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                    <div>
                        <h2 id="tour-casos-title" class="text-2xl font-black text-gray-900 dark:text-white flex items-center gap-2">
                            <ArchiveBoxXMarkIcon class="w-8 h-8 text-indigo-500" />
                            Gestión de Casos
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Administra y haz seguimiento a todos los procesos jurídicos.
                        </p>
                    </div>
                    <div class="flex items-center gap-3 w-full md:w-auto">
                        <Link
                            id="tour-btn-importar"
                            :href="route('casos.import.show')"
                            class="flex-1 md:flex-none inline-flex items-center justify-center px-4 py-2.5 bg-white border border-gray-200 text-gray-600 rounded-lg font-bold text-sm transition-all shadow-sm hover:bg-gray-50"
                        >
                            <CloudArrowUpIcon class="w-5 h-5 mr-2" />
                            Importar
                        </Link>
                        <button 
                            id="tour-btn-exportar"
                            @click="exportarExcel" 
                            class="flex-1 md:flex-none inline-flex items-center justify-center px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white rounded-lg font-bold text-sm transition-all shadow-sm shadow-green-200 dark:shadow-none"
                        >
                            <ArrowDownTrayIcon class="w-5 h-5 mr-2" />
                            Exportar Excel
                        </button>
                        <Link
                            :href="route('casos.create')"
                            class="flex-1 md:flex-none inline-flex items-center justify-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-bold text-sm transition-all shadow-sm shadow-indigo-200 dark:shadow-none"
                        >
                            + Registrar Caso
                        </Link>
                    </div>
                </div>

                <!-- STATS / KPI CARDS -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 relative overflow-hidden group transition-all hover:shadow-md">
                        <div class="absolute -right-2 -top-2 opacity-5 group-hover:scale-110 transition-transform">
                            <ArchiveBoxXMarkIcon class="w-16 h-16 text-indigo-600" />
                        </div>
                        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Total Casos</p>
                        <div v-if="!stats" class="h-8 w-16 bg-gray-100 dark:bg-gray-700 animate-pulse rounded"></div>
                        <p v-else class="text-2xl font-black text-gray-900 dark:text-white">{{ stats.total }}</p>
                        <p class="text-[9px] text-gray-500 font-bold mt-1 uppercase tracking-tighter">En base de datos</p>
                    </div>

                    <div class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 relative overflow-hidden group transition-all hover:shadow-md">
                        <div class="absolute -right-2 -top-2 opacity-5 group-hover:scale-110 transition-transform text-amber-600">
                            <ExclamationCircleIcon class="w-16 h-16" />
                        </div>
                        <p class="text-[10px] font-black text-amber-600 uppercase tracking-widest mb-1">Sin Radicado</p>
                        <div v-if="!stats" class="h-8 w-16 bg-gray-100 dark:bg-gray-700 animate-pulse rounded"></div>
                        <p v-else class="text-2xl font-black text-gray-900 dark:text-white">{{ stats.sin_radicado }}</p>
                        <p class="text-[9px] text-amber-500 font-bold mt-1 uppercase tracking-tighter">Pendientes de trámite</p>
                    </div>

                    <div class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 relative overflow-hidden group transition-all hover:shadow-md">
                        <div class="absolute -right-2 -top-2 opacity-5 group-hover:scale-110 transition-transform text-green-600">
                            <BanknotesIcon class="w-16 h-16" />
                        </div>
                        <p class="text-[10px] font-black text-green-600 uppercase tracking-widest mb-1">Saldo Recuperable</p>
                        <div v-if="!stats" class="h-8 w-32 bg-gray-100 dark:bg-gray-700 animate-pulse rounded"></div>
                        <p v-else class="text-2xl font-black text-gray-900 dark:text-white">{{ formatCurrency(stats.saldo_total) }}</p>
                        <p class="text-[9px] text-green-500 font-bold mt-1 uppercase tracking-tighter">Cartera total pendiente</p>
                    </div>

                    <div class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 relative overflow-hidden group transition-all hover:shadow-md">
                        <div class="absolute -right-2 -top-2 opacity-5 group-hover:scale-110 transition-transform text-indigo-600">
                            <ArrowPathIcon class="w-16 h-16" />
                        </div>
                        <p class="text-[10px] font-black text-indigo-600 uppercase tracking-widest mb-1">Actualizados Hoy</p>
                        <div v-if="!stats" class="h-8 w-16 bg-gray-100 dark:bg-gray-700 animate-pulse rounded"></div>
                        <p v-else class="text-2xl font-black text-gray-900 dark:text-white">{{ stats.actualizados_hoy }}</p>
                        <p class="text-[9px] text-indigo-500 font-bold mt-1 uppercase tracking-tighter">Gestiones en las últimas 24h</p>
                    </div>
                </div>

                <!-- Barra de Filtros Avanzada -->
                <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4">
                        <!-- Búsqueda -->
                        <div class="relative">
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1 ml-1">Búsqueda rápida</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <MagnifyingGlassIcon class="h-4 w-4 text-gray-400" />
                                </div>
                                <input
                                    v-model="filterForm.search"
                                    type="text"
                                    class="block w-full pl-10 pr-3 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-sm focus:ring-indigo-500 focus:border-indigo-500 dark:text-white"
                                    placeholder="Nombre, cédula, radicado..."
                                />
                            </div>
                        </div>

                        <!-- Cooperativa -->
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1 ml-1">Cooperativa</label>
                            <SelectInput v-model="filterForm.cooperativa_id">
                                <option value="">Todas</option>
                                <option v-for="c in cooperativas" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                            </SelectInput>
                        </div>

                        <!-- Tipo Entidad -->
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1 ml-1">Tipo de Entidad</label>
                            <SelectInput v-model="filterForm.tipo_entidad">
                                <option value="">Todas las Entidades</option>
                                <option v-for="tipo in tiposEntidad" :key="tipo" :value="tipo">{{ tipo }}s</option>
                            </SelectInput>
                        </div>

                        <!-- Juzgado -->
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1 ml-1">Juzgado Específico</label>
                            <AsyncSelect 
                                v-model="selectedJuzgado"
                                :endpoint="route('juzgados.search')"
                                placeholder="Buscar juzgado..."
                                label-key="nombre"
                                class="!min-h-[38px]"
                            />
                        </div>

                        <!-- Abogado -->
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1 ml-1">Responsable</label>
                            <SelectInput v-model="filterForm.abogado_id">
                                <option value="">Todos</option>
                                <option v-for="a in abogados" :key="a.id" :value="a.id">{{ a.name }}</option>
                            </SelectInput>
                        </div>

                        <!-- Etapa -->
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1 ml-1">Etapa Procesal</label>
                            <div class="flex items-center gap-2">
                                <SelectInput v-model="filterForm.etapa_procesal">
                                    <option value="">Todas</option>
                                    <option v-for="e in etapas_procesales" :key="e" :value="e">{{ e }}</option>
                                </SelectInput>
                                <label class="flex items-center gap-1.5 cursor-pointer shrink-0 bg-gray-50 dark:bg-gray-700 px-2 py-2 rounded-lg border border-gray-100 dark:border-gray-600 h-[38px]">
                                    <input 
                                        v-model="filterForm.sin_radicado"
                                        type="checkbox" 
                                        class="rounded text-indigo-600 focus:ring-indigo-500 border-gray-300 h-4 w-4"
                                    />
                                    <span class="text-[9px] font-black uppercase text-gray-500">Sin Rad.</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Botón Limpiar y Resultados -->
                    <div class="flex justify-between items-center pt-2 border-t border-gray-50 dark:border-gray-700">
                        <div class="text-sm text-gray-500 dark:text-gray-400 font-medium">
                            Mostrando <span class="text-indigo-600 dark:text-indigo-400 font-bold">{{ casos.total }}</span> resultados
                        </div>
                        <button 
                            v-if="isDirty"
                            @click="resetFilters"
                            class="inline-flex items-center text-xs font-bold text-red-500 hover:text-red-700 transition-colors"
                        >
                            <XMarkIcon class="w-4 h-4 mr-1" />
                            Limpiar Filtros
                        </button>
                    </div>
                </div>

                <!-- Tabla de Casos -->
                <div class="bg-white dark:bg-gray-800 shadow-xl rounded-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                    <div class="overflow-x-auto custom-scrollbar-horizontal">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50/50 dark:bg-gray-700/50">
                                <tr>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-black text-gray-400 uppercase tracking-wider">
                                        Deudor y Expediente
                                    </th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-black text-gray-400 uppercase tracking-wider">
                                        Cooperativa
                                    </th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-black text-gray-400 uppercase tracking-wider">
                                        Etapa Procesal
                                    </th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-black text-gray-400 uppercase tracking-wider">
                                        Responsable
                                    </th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-black text-gray-400 uppercase tracking-wider">
                                        Actualización
                                    </th>
                                    <th scope="col" class="sticky right-0 bg-gray-50 dark:bg-gray-700 px-6 py-4 text-right text-xs font-black text-gray-400 uppercase tracking-wider z-10 shadow-[-4px_0_6px_-2px_rgba(0,0,0,0.05)]">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700 bg-white dark:bg-gray-800">
                                <tr v-if="casos.data.length === 0">
                                    <td colspan="6" class="px-6 py-20 text-center">
                                        <div class="flex flex-col items-center">
                                            <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-full mb-4">
                                                <InboxIcon class="h-10 w-10 text-gray-400" />
                                            </div>
                                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">No se encontraron casos</h3>
                                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-xs mx-auto">
                                                No hay registros que coincidan con los filtros aplicados.
                                            </p>
                                            <button @click="resetFilters" class="mt-4 text-indigo-600 font-bold hover:underline">Limpiar filtros</button>
                                        </div>
                                    </td>
                                </tr>
                                
                                <tr v-else v-for="caso in casos.data" :key="caso.id" class="hover:bg-indigo-50/30 dark:hover:bg-indigo-900/10 transition-colors group cursor-pointer" :class="{'bg-indigo-50/50 dark:bg-indigo-900/20': caso.is_pinned}" @click="openQuickView(caso)">
                                    <!-- Deudor / Expediente -->
                                    <td class="px-6 py-4 relative">
                                        <div v-if="caso.is_pinned" class="absolute left-0 top-0 bottom-0 w-1 bg-indigo-500 rounded-r"></div>
                                        <div class="flex flex-col">
                                            <Link @click.stop :href="route('casos.show', caso.id)" class="text-sm font-black text-indigo-600 dark:text-indigo-400 hover:underline">
                                                {{ caso.deudor?.nombre_completo ?? 'Sin deudor' }}
                                            </Link>
                                            <div class="flex items-center gap-1.5 mt-1">
                                                <span class="text-[10px] font-bold px-1.5 py-0.5 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 rounded">
                                                    ID #{{ caso.id }}
                                                </span>
                                                <span v-if="caso.radicado" class="text-[10px] text-gray-400 flex items-center gap-1 group/rad">
                                                    Rad: {{ caso.radicado }}
                                                    <button @click.stop="copyToClipboard(caso.radicado)" class="opacity-0 group-hover/rad:opacity-100 transition-opacity">
                                                        <DocumentDuplicateIcon class="w-3 h-3 hover:text-indigo-500" />
                                                    </button>
                                                </span>
                                                <span v-else class="text-[10px] font-bold text-amber-600 italic">Pendiente Radicado</span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Cooperativa -->
                                    <td class="px-6 py-4">
                                        <span class="text-xs font-medium text-gray-600 dark:text-gray-300">
                                            {{ caso.cooperativa?.nombre ?? 'N/A' }}
                                        </span>
                                    </td>

                                    <!-- Etapa Procesal -->
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col gap-1 items-start">
                                            <span 
                                                class="px-2.5 py-1 text-[10px] font-black rounded-lg border leading-none uppercase tracking-wider"
                                                :class="getEtapaColor(caso.etapa_procesal || caso.estado_proceso)"
                                            >
                                                {{ caso.etapa_procesal || caso.estado_proceso || 'SIN ETAPA' }}
                                            </span>
                                            <span v-if="caso.nota_cierre" class="text-[9px] font-bold text-red-500 px-1 border border-red-200 rounded">CERRADO</span>
                                        </div>
                                    </td>

                                    <!-- Responsable -->
                                    <td class="px-6 py-4">
                                        <div class="flex -space-x-2 overflow-hidden">
                                            <template v-if="caso.users && caso.users.length > 0">
                                                <div 
                                                    v-for="u in caso.users" 
                                                    :key="u.id" 
                                                    @click.stop
                                                    class="inline-flex items-center justify-center h-8 w-8 rounded-full ring-2 ring-white dark:ring-gray-800 bg-indigo-100 dark:bg-indigo-900/50 border border-indigo-200 dark:border-indigo-800 transition-transform hover:z-10 hover:scale-110 cursor-help"
                                                    :title="u.name"
                                                >
                                                    <span class="text-[10px] font-black text-indigo-700 dark:text-indigo-300 uppercase">
                                                        {{ u.name.split(' ').map(n => n[0]).join('').substring(0, 2) }}
                                                    </span>
                                                </div>
                                            </template>
                                            <span v-else class="text-[10px] text-gray-400 font-bold italic">Sin asignar</span>
                                        </div>
                                    </td>

                                    <!-- Actualización -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex flex-col">
                                            <span class="text-xs font-bold text-gray-700 dark:text-gray-300">{{ formatDate(caso.updated_at) }}</span>
                                            <span class="text-[10px] text-gray-400 italic">hace {{ formatTimeAgo(caso.updated_at) }}</span>
                                            
                                            <div v-if="getInactivityDays(caso.updated_at) >= 30 && !caso.nota_cierre" class="mt-1">
                                                <span class="px-1.5 py-0.5 bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 text-[8px] font-black uppercase rounded border border-red-100 dark:border-red-800 animate-pulse">
                                                    ⚠️ Inactivo {{ getInactivityDays(caso.updated_at) }}d
                                                </span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Acciones Sticky -->
                                    <td class="sticky right-0 bg-white dark:bg-gray-800 group-hover:bg-indigo-50/50 dark:group-hover:bg-gray-700 px-6 py-4 whitespace-nowrap text-right z-10 shadow-[-4px_0_6px_-2px_rgba(0,0,0,0.05)] transition-colors" :class="{'!bg-indigo-50/50 dark:!bg-indigo-900/20': caso.is_pinned}" @click.stop>
                                        <div class="flex items-center justify-end gap-2">
                                            <button 
                                                @click.stop="togglePin(caso)"
                                                class="p-2 rounded-lg transition-all shadow-sm"
                                                :class="caso.is_pinned ? 'text-white bg-indigo-600 hover:bg-indigo-700' : 'text-gray-400 bg-gray-50 hover:bg-gray-100 dark:bg-gray-700 dark:hover:bg-gray-600'"
                                                :title="caso.is_pinned ? 'Desfijar' : 'Fijar al inicio'"
                                            >
                                                <PinIcon class="h-5 w-5" :class="{'rotate-45': !caso.is_pinned}" />
                                            </button>
                                            <Link
                                                :href="route('casos.show', caso.id)"
                                                class="p-2 text-indigo-500 bg-indigo-50 dark:bg-indigo-900/30 rounded-lg hover:bg-indigo-600 hover:text-white transition-all shadow-sm"
                                                title="Ver detalle completo"
                                            >
                                                <EyeIcon class="h-5 w-5" />
                                            </Link>
                                            
                                            <button
                                                v-if="$page.props.auth.user.tipo_usuario === 'admin'"
                                                @click="confirmCaseDeletion(caso)"
                                                class="p-2 text-red-500 bg-red-50 dark:bg-red-900/30 rounded-lg hover:bg-red-600 hover:text-white transition-all shadow-sm"
                                                title="Eliminar registro"
                                            >
                                                <TrashIcon class="h-5 w-5" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Paginación -->
                    <div v-if="casos && casos.links?.length > 3" class="p-4 flex flex-wrap justify-center gap-2 border-t dark:border-gray-700">
                        <Link
                            v-for="(link, idx) in casos.links"
                            :key="idx"
                            :href="link.url || '#'"
                            v-html="link.label"
                            class="px-3 py-2 text-sm rounded-md border dark:border-gray-600"
                            :class="{
                                'bg-gray-200 dark:bg-gray-700 text-gray-500 dark:text-gray-400 pointer-events-none': !link.url,
                                'bg-indigo-600 text-white border-indigo-600': link.active,
                                'hover:bg-gray-100 dark:hover:bg-gray-600': link.url && !link.active
                            }"
                        />
                    </div>
                </div>

                <!-- Móvil: Cards Modernas -->
                <div class="md:hidden space-y-4">
                    <div v-for="caso in casos.data" :key="'mob-'+caso.id" @click="openQuickView(caso)" class="bg-white dark:bg-gray-800 p-5 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 relative overflow-hidden cursor-pointer">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <Link @click.stop :href="route('casos.show', caso.id)" class="text-sm font-black text-indigo-600 dark:text-indigo-400 hover:underline">
                                    {{ caso.deudor?.nombre_completo ?? 'Sin deudor' }}
                                </Link>
                                <div class="flex items-center gap-1.5 mt-1">
                                    <span class="text-[10px] font-bold px-1.5 py-0.5 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 rounded">
                                        ID #{{ caso.id }}
                                    </span>
                                </div>
                            </div>
                            <span class="px-2 py-1 text-[9px] font-black rounded-lg border uppercase tracking-tighter shadow-sm" :class="getEtapaColor(caso.etapa_procesal || caso.estado_proceso)">
                                {{ caso.etapa_procesal || caso.estado_proceso || 'SIN ETAPA' }}
                            </span>
                        </div>

                        <div class="space-y-3 mb-6">
                            <div class="flex items-start gap-2">
                                <span class="text-[9px] font-black text-blue-600 bg-blue-50 px-1 rounded border border-blue-100 mt-0.5">EMP</span>
                                <p class="text-xs font-bold text-gray-700 dark:text-gray-300 leading-tight">{{ caso.cooperativa?.nombre ?? 'N/A' }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3 pt-4 border-t border-gray-50 dark:border-gray-700">
                            <Link @click.stop :href="route('casos.edit', caso.id)" class="flex items-center justify-center py-2 bg-gray-50 dark:bg-gray-700 text-[10px] font-black uppercase rounded-xl text-gray-500">Editar</Link>
                            <Link @click.stop :href="route('casos.show', caso.id)" class="flex items-center justify-center py-2 bg-indigo-600 text-[10px] font-black uppercase rounded-xl text-white shadow-lg shadow-indigo-100">Ver Ficha</Link>
                        </div>
                    </div>
                </div>

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

        <!-- MODAL DE VISTA RÁPIDA (V5: Ficha Optimizada y Responsiva) -->
        <Modal :show="showQuickViewModal" @close="closeQuickView" max-width="3xl" centered>
            <div v-if="selectedCaso" class="overflow-hidden rounded-2xl bg-white dark:bg-gray-900 shadow-2xl transition-all border border-gray-100 dark:border-gray-800 flex flex-col h-[80vh] sm:h-[82vh]">
                <!-- Header: Título y Navegación (Fijo) -->
                <div class="px-4 py-3 sm:px-8 sm:py-5 bg-indigo-600 dark:bg-indigo-700 text-white flex justify-between items-center shrink-0 shadow-lg relative z-10 w-full">
                    <div class="flex items-center gap-3 sm:gap-4 overflow-hidden min-w-0">
                        <div class="hidden sm:flex h-10 w-10 bg-white/10 rounded-xl items-center justify-center border border-white/20 shrink-0">
                            <ArchiveBoxXMarkIcon class="w-6 h-6" />
                        </div>
                        <div class="truncate min-w-0">
                            <p class="text-[9px] sm:text-[10px] font-black uppercase tracking-widest text-indigo-100/70 truncate">Expediente #{{ selectedCaso.id }}</p>
                            <h2 class="text-base sm:text-lg font-black leading-tight truncate">{{ selectedCaso.deudor?.nombre_completo }}</h2>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 sm:gap-4 shrink-0 ml-2">
                        <button @click="copyLegalInfo()" class="hidden sm:flex items-center gap-2 px-4 py-2 bg-white/10 hover:bg-white/20 rounded-xl transition-all text-[10px] font-black uppercase tracking-widest border border-white/30 shadow-inner active:scale-95">
                            <DocumentDuplicateIcon class="w-4 h-4" /> Copiar Info Legal
                        </button>
                        <div class="flex bg-black/10 rounded-lg p-0.5">
                            <button @click="prevCaso" :disabled="currentIndex === 0" class="p-1.5 sm:p-1.5 hover:bg-white/10 disabled:opacity-20 rounded-md transition-all active:scale-90" title="Anterior">
                                <ChevronDownIcon class="w-4 h-4 rotate-90" />
                            </button>
                            <div class="px-1.5 sm:px-3 flex items-center border-x border-white/5">
                                <span class="text-[9px] sm:text-[10px] font-black tracking-widest">{{ currentIndex + 1 }}<span class="opacity-50 mx-0.5">/</span>{{ casos.data.length }}</span>
                            </div>
                            <button @click="nextCaso" :disabled="currentIndex === casos.data.length - 1" class="p-1.5 sm:p-1.5 hover:bg-white/10 disabled:opacity-20 rounded-md transition-all active:scale-90" title="Siguiente">
                                <ChevronDownIcon class="w-4 h-4 -rotate-90" />
                            </button>
                        </div>
                        <button @click="closeQuickView" class="p-1.5 sm:p-2 hover:bg-white/10 rounded-lg transition-colors">
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </div>
                </div>

                <!-- Cuerpo: Todos los detalles con Scroll Interno -->
                <div class="flex-grow overflow-y-auto p-4 sm:p-8 custom-scrollbar space-y-8 sm:space-y-10 bg-gray-50/30 dark:bg-gray-900/50 w-full">
                    
                    <!-- 1. IDENTIFICACIÓN Y CONTACTO -->
                    <section class="space-y-4">
                        <div class="flex items-center gap-2 border-b border-gray-100 dark:border-gray-800 pb-2">
                            <UserIcon class="w-4 h-4 text-indigo-500" />
                            <h3 class="text-[10px] sm:text-xs font-black text-gray-900 dark:text-white uppercase tracking-widest">Partes Involucradas</h3>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6 bg-white dark:bg-gray-800 p-4 sm:p-6 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm">
                            <div class="space-y-3 min-w-0">
                                <div class="group/item">
                                    <p class="text-[9px] font-bold text-gray-400 uppercase mb-0.5">Titular Principal</p>
                                    <p class="text-sm font-black text-gray-900 dark:text-white group-hover/item:text-indigo-600 transition-colors break-words">{{ selectedCaso.deudor?.nombre_completo || 'No especificado' }}</p>
                                </div>
                                <div>
                                    <p class="text-[9px] font-bold text-gray-400 uppercase mb-0.5">Identificación</p>
                                    <p class="text-xs font-bold text-gray-700 dark:text-gray-300">{{ selectedCaso.deudor?.numero_documento || 'Sin documento' }}</p>
                                </div>
                                <div>
                                    <p class="text-[9px] font-bold text-gray-400 uppercase mb-0.5">Cooperativa</p>
                                    <p class="text-xs font-extrabold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 px-2 py-0.5 rounded-md inline-block max-w-full truncate">{{ selectedCaso.cooperativa?.nombre || 'N/A' }}</p>
                                </div>
                            </div>
                            <div class="space-y-3 pt-3 md:pt-0 border-t md:border-t-0 border-gray-100 dark:border-gray-700 min-w-0">
                                <div>
                                    <p class="text-[9px] font-bold text-gray-400 uppercase mb-0.5">Celular / Teléfono</p>
                                    <p class="text-xs font-bold text-gray-700 dark:text-gray-300 flex items-center gap-2">
                                        <PhoneIcon class="w-3.5 h-3.5 text-gray-400 shrink-0" />
                                        {{ selectedCaso.deudor?.celular_1 || 'No registrado' }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-[9px] font-bold text-gray-400 uppercase mb-0.5">Correo Electrónico</p>
                                    <p class="text-xs font-bold text-gray-700 dark:text-gray-300 flex items-center gap-2 truncate">
                                        <EnvelopeIcon class="w-3.5 h-3.5 text-gray-400 shrink-0" />
                                        {{ selectedCaso.deudor?.correo_1 || 'Sin correo' }}
                                    </p>
                                </div>
                                <div v-if="selectedCaso.codeudores?.length">
                                    <p class="text-[9px] font-bold text-gray-400 uppercase mb-1.5">Codeudores ({{ selectedCaso.codeudores.length }})</p>
                                    <div class="flex flex-wrap gap-1.5">
                                        <span v-for="c in selectedCaso.codeudores" :key="c.id" class="text-[9px] font-black px-2 py-1 bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600 rounded-lg uppercase text-gray-600 dark:text-gray-400">
                                            {{ c.nombre_completo.split(' ')[0] }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- 2. DETALLES JUDICIALES COMPLETOS -->
                    <section class="space-y-4">
                        <div class="flex items-center gap-2 border-b border-gray-100 dark:border-gray-800 pb-2">
                            <ScaleIcon class="w-4 h-4 text-indigo-500" />
                            <h3 class="text-[10px] sm:text-xs font-black text-gray-900 dark:text-white uppercase tracking-widest">Detalles del Proceso Judicial</h3>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 px-1">
                            <div class="space-y-1 min-w-0">
                                <p class="text-[9px] font-bold text-gray-400 uppercase">Radicado Judicial</p>
                                <p class="text-xs font-black text-gray-800 dark:text-gray-200 flex items-center gap-2 break-all">
                                    {{ selectedCaso.radicado || 'SIN ASIGNAR' }}
                                    <button v-if="selectedCaso.radicado" @click.stop="copyToClipboard(selectedCaso.radicado)" class="text-gray-400 hover:text-indigo-500 transition-colors shrink-0">
                                        <DocumentDuplicateIcon class="w-3.5 h-3.5" />
                                    </button>
                                </p>
                            </div>
                            <div class="space-y-1 min-w-0">
                                <p class="text-[9px] font-bold text-gray-400 uppercase">Número de Pagaré</p>
                                <p class="text-xs font-black text-gray-800 dark:text-gray-200 flex items-center gap-2 break-all">
                                    {{ selectedCaso.referencia_credito || 'SIN ASIGNAR' }}
                                    <button v-if="selectedCaso.referencia_credito" @click.stop="copyToClipboard(selectedCaso.referencia_credito)" class="text-gray-400 hover:text-indigo-500 transition-colors shrink-0">
                                        <DocumentDuplicateIcon class="w-3.5 h-3.5" />
                                    </button>
                                </p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-[9px] font-bold text-gray-400 uppercase">Estado / Etapa</p>
                                <p class="text-xs font-black text-indigo-600 dark:text-indigo-400 uppercase break-words">{{ selectedCaso.etapa_procesal || selectedCaso.estado_proceso || 'N/A' }}</p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-[9px] font-bold text-gray-400 uppercase">Especialidad</p>
                                <p class="text-xs font-black text-gray-800 dark:text-gray-200 uppercase">{{ selectedCaso.especialidad?.nombre || 'CIVIL' }}</p>
                            </div>
                            <div class="sm:col-span-2 lg:col-span-3 space-y-1 bg-gray-50 dark:bg-gray-800/50 p-3 rounded-xl border border-gray-100 dark:border-gray-700">
                                <p class="text-[9px] font-bold text-gray-400 uppercase">Juzgado</p>
                                <p class="text-xs font-bold text-gray-800 dark:text-gray-200 flex items-center gap-2 break-words">
                                    <BuildingOfficeIcon class="w-3.5 h-3.5 text-gray-400 shrink-0" />
                                    {{ selectedCaso.juzgado?.nombre || 'POR DEFINIR DESPACHO' }}
                                </p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-[9px] font-bold text-gray-400 uppercase">Tipo Proceso</p>
                                <p class="text-xs font-bold text-gray-800 dark:text-gray-200">{{ selectedCaso.tipo_proceso || 'N/A' }}</p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-[9px] font-bold text-gray-400 uppercase">Subproceso</p>
                                <p class="text-xs font-bold text-gray-800 dark:text-gray-200">{{ selectedCaso.subproceso || 'N/A' }}</p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-[9px] font-bold text-gray-400 uppercase">Etapa Actual</p>
                                <p class="text-xs font-bold text-gray-800 dark:text-gray-200">{{ selectedCaso.etapa_actual || 'N/A' }}</p>
                            </div>
                        </div>
                        <div class="p-4 bg-amber-50/50 dark:bg-amber-900/10 border border-amber-100 dark:border-amber-900/30 rounded-2xl grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <p class="text-[9px] font-bold text-amber-600 uppercase mb-0.5">Garantía Asociada</p>
                                <p class="text-xs font-black text-amber-800 dark:text-amber-400">{{ selectedCaso.tipo_garantia_asociada || 'Sin garantía registrada' }}</p>
                            </div>
                            <div>
                                <p class="text-[9px] font-bold text-amber-600 uppercase mb-0.5">Origen Documental</p>
                                <p class="text-xs font-black text-amber-800 dark:text-amber-400 uppercase tracking-tighter">{{ selectedCaso.origen_documental || 'N/A' }}</p>
                            </div>
                        </div>
                    </section>

                    <!-- 3. RESUMEN FINANCIERO -->
                    <section class="space-y-4">
                        <div class="flex items-center gap-2 border-b border-gray-100 dark:border-gray-800 pb-2">
                            <BanknotesIcon class="w-4 h-4 text-indigo-500" />
                            <h3 class="text-[10px] sm:text-xs font-black text-gray-900 dark:text-white uppercase tracking-widest">Situación Económica</h3>
                        </div>
                        <div class="bg-gray-900 dark:bg-black p-5 sm:p-7 rounded-3xl text-white relative overflow-hidden shadow-xl w-full">
                            <div class="absolute right-0 top-0 p-4 opacity-5 pointer-events-none">
                                <BanknotesIcon class="w-32 h-32 rotate-12" />
                            </div>
                            <div class="relative z-10">
                                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-indigo-400 mb-2">Deuda Pendiente Actual</p>
                                <h4 class="text-xl sm:text-4xl font-black tracking-tight break-all">{{ formatCurrency(selectedCaso.monto_total - selectedCaso.monto_total_pagado) }}</h4>
                                
                                <div class="grid grid-cols-1 xs:grid-cols-2 gap-4 sm:gap-8 mt-8 border-t border-white/10 pt-5">
                                    <div class="min-w-0">
                                        <p class="text-[9px] font-black text-gray-500 uppercase tracking-wider mb-1">Obligación Base</p>
                                        <p class="text-sm font-black text-gray-200 truncate">{{ formatCurrency(selectedCaso.monto_total) }}</p>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-[9px] font-black text-green-500 uppercase tracking-wider mb-1">Total Recaudado</p>
                                        <p class="text-sm font-black text-green-400 truncate">{{ formatCurrency(selectedCaso.monto_total_pagado) }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 xs:grid-cols-2 md:grid-cols-4 gap-4 px-1 pt-2">
                            <div class="bg-white dark:bg-gray-800 p-3 rounded-xl border border-gray-100 dark:border-gray-700">
                                <p class="text-[9px] font-bold text-gray-400 uppercase mb-0.5">Tasa Interés</p>
                                <p class="text-xs font-black text-gray-800 dark:text-gray-200">{{ selectedCaso.tasa_interes_corriente || '0.00' }}%</p>
                            </div>
                            <div class="bg-white dark:bg-gray-800 p-3 rounded-xl border border-gray-100 dark:border-gray-700">
                                <p class="text-[9px] font-bold text-gray-400 uppercase mb-0.5">Apertura</p>
                                <p class="text-xs font-black text-gray-800 dark:text-gray-200">{{ formatDate(selectedCaso.fecha_apertura) }}</p>
                            </div>
                            <div class="bg-white dark:bg-gray-800 p-3 rounded-xl border border-gray-100 dark:border-gray-700">
                                <p class="text-[9px] font-bold text-gray-400 uppercase mb-0.5">Vencimiento</p>
                                <p class="text-xs font-black text-gray-800 dark:text-gray-200">{{ formatDate(selectedCaso.fecha_vencimiento) }}</p>
                            </div>
                            <div class="bg-red-50 dark:bg-red-900/10 p-3 rounded-xl border border-red-100 dark:border-red-900/20">
                                <p class="text-[9px] font-black text-red-500 uppercase mb-0.5">Días en Mora</p>
                                <p class="text-xs font-black text-red-600 dark:text-red-400">{{ selectedCaso.dias_en_mora || 0 }} días</p>
                            </div>
                        </div>
                    </section>

                    <!-- 4. GESTIÓN Y NOTAS LEGALES -->
                    <section class="space-y-4">
                        <div class="flex items-center gap-2 border-b border-gray-100 dark:border-gray-800 pb-2">
                            <ClipboardDocumentListIcon class="w-4 h-4 text-indigo-500" />
                            <h3 class="text-[10px] sm:text-xs font-black text-gray-900 dark:text-white uppercase tracking-widest">Gestión Técnica</h3>
                        </div>
                        <div class="space-y-6 px-1">
                            <div>
                                <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mb-3">Abogados Asignados</p>
                                <div class="flex flex-wrap gap-2">
                                    <div v-for="u in selectedCaso.users" :key="u.id" class="flex items-center gap-2 px-3 py-1.5 bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300 text-[10px] font-black uppercase rounded-xl border border-indigo-100 dark:border-indigo-800">
                                        <div class="w-2 h-2 rounded-full bg-indigo-500 animate-pulse"></div>
                                        {{ u.name }}
                                    </div>
                                    <span v-if="!selectedCaso.users?.length" class="text-xs italic text-gray-400">Sin abogados asignados</span>
                                </div>
                            </div>
                            
                            <div v-if="selectedCaso.notas_legales">
                                <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mb-2">Observaciones de Proceso</p>
                                <div class="bg-white dark:bg-gray-800 p-4 rounded-2xl border border-gray-100 dark:border-gray-700 border-l-4 border-l-indigo-500">
                                    <p class="text-xs text-gray-600 dark:text-gray-400 italic leading-relaxed">{{ selectedCaso.notas_legales }}</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
                                <a v-if="selectedCaso.link_drive" :href="selectedCaso.link_drive" target="_blank" class="flex items-center justify-center gap-2 p-3.5 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all shadow-lg shadow-blue-200 dark:shadow-none active:scale-95">
                                    <LinkIcon class="w-4 h-4" /> Carpeta Drive
                                </a>
                                <a v-if="selectedCaso.link_expediente" :href="selectedCaso.link_expediente" target="_blank" class="flex items-center justify-center gap-2 p-3.5 bg-purple-600 hover:bg-purple-700 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all shadow-lg shadow-purple-200 dark:shadow-none active:scale-95">
                                    <CloudArrowUpIcon class="w-4 h-4" /> Expediente Digital
                                </a>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- Footer: Acción de Ingreso Full (Fijo) -->
                <div class="px-4 py-4 sm:px-8 sm:py-5 bg-white dark:bg-gray-800 border-t border-gray-100 dark:border-gray-700 flex flex-col sm:flex-row justify-between items-center gap-4 shrink-0 shadow-[0_-4px_10px_rgba(0,0,0,0.03)] relative z-10">
                    <p class="text-[10px] font-bold text-gray-400 italic flex items-center gap-2">
                        <ClockIcon class="w-3.5 h-3.5" /> 
                        Última actualización: {{ formatDate(selectedCaso.updated_at) }} (hace {{ formatTimeAgo(selectedCaso.updated_at) }})
                    </p>
                    <Link :href="route('casos.show', selectedCaso.id)" class="w-full sm:w-auto px-8 py-3.5 bg-gray-900 dark:bg-white text-white dark:text-gray-900 rounded-2xl font-black text-[11px] uppercase tracking-[0.2em] transition-all hover:bg-black dark:hover:bg-gray-100 shadow-xl shadow-gray-200 dark:shadow-none active:scale-[0.98] text-center">
                        Ver Expediente Completo &rarr;
                    </Link>
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
