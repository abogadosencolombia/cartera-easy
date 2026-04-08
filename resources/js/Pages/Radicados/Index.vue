<script setup>
import { ref, watch, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TextInput from '@/Components/TextInput.vue';
import Pagination from '@/Components/Pagination.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import Textarea from '@/Components/Textarea.vue';
import { debounce } from 'lodash';
import { useProcesos } from '@/composables/useProcesos';
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
    BuildingOfficeIcon,
    PhoneIcon,
    EnvelopeIcon,
    ClipboardDocumentListIcon,
    LinkIcon,
    PencilSquareIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    procesos: { type: Object, required: true },
    filtros: { type: Object, default: () => ({}) },
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
const search = ref(props.filtros.search || '');
const estado = ref(props.filtros.estado || 'TODOS');
const tipoEntidad = ref(props.filtros.tipo_entidad || '');
const sinRadicado = ref(props.filtros.sin_radicado === 'true' || props.filtros.sin_radicado === true);

const isDirty = computed(() => {
    return search.value !== '' || estado.value !== 'TODOS' || tipoEntidad.value !== '' || sinRadicado.value === true;
});

const resetFilters = () => {
    search.value = '';
    estado.value = 'TODOS';
    tipoEntidad.value = '';
    sinRadicado.value = false;
};

const applyFilters = debounce(() => {
    router.get(route('procesos.index'), { 
        search: search.value, 
        estado: estado.value === 'TODOS' ? '' : estado.value,
        tipo_entidad: tipoEntidad.value,
        sin_radicado: sinRadicado.value
    }, { preserveState: true, replace: true });
}, 300);

watch([search, estado, tipoEntidad, sinRadicado], applyFilters);

// --- EXPORTAR ---
const exportarExcel = () => {
    const params = new URLSearchParams({
        search: search.value,
        estado: estado.value === 'TODOS' ? '' : estado.value,
        tipo_entidad: tipoEntidad.value,
        sin_radicado: sinRadicado.value
    });
    window.location.href = route('procesos.exportar') + '?' + params.toString();
};

// --- UTILIDADES ---
const copyToClipboard = (text) => {
    if (!text) return;
    navigator.clipboard.writeText(text);
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
</script>

<template>
    <Head title="Gestión de Radicados" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                <div class="flex items-center gap-2">
                    <h2 class="text-2xl font-black text-gray-900 dark:text-white flex items-center gap-2">
                        <ScaleIcon class="w-8 h-8 text-indigo-500" />
                        Expedientes Judiciales
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Gestión y seguimiento detallado de procesos radicados.
                    </p>
                </div>
                <div class="flex items-center gap-3 w-full md:w-auto">
                    <Link
                        :href="route('procesos.import.show')"
                        class="flex-1 md:flex-none inline-flex items-center justify-center px-4 py-2.5 bg-white border border-gray-200 text-gray-600 rounded-lg font-bold text-sm transition-all shadow-sm hover:bg-gray-50"
                    >
                        <CloudArrowUpIcon class="w-5 h-5 mr-2" />
                        Importar
                    </Link>
                    <button 
                        @click="exportarExcel" 
                        class="flex-1 md:flex-none inline-flex items-center justify-center px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white rounded-lg font-bold text-sm transition-all shadow-sm shadow-green-200 dark:shadow-none"
                    >
                        <ArrowDownTrayIcon class="w-5 h-5 mr-2" />
                        Exportar Excel
                    </button>
                    <Link
                        :href="route('procesos.create')"
                        class="flex-1 md:flex-none inline-flex items-center justify-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-bold text-sm transition-all shadow-sm shadow-indigo-200 dark:shadow-none"
                    >
                        + Nuevo Radicado
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-8xl mx-auto sm:px-6 lg:px-8 space-y-4">
                
                <!-- Barra de Filtros Avanzada -->
                <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <!-- Búsqueda -->
                        <div class="relative lg:col-span-2">
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1 ml-1">Búsqueda rápida</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <MagnifyingGlassIcon class="h-4 w-4 text-gray-400" />
                                </div>
                                <input
                                    v-model="search"
                                    type="text"
                                    class="block w-full pl-10 pr-3 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-sm focus:ring-indigo-500 focus:border-indigo-500 dark:text-white"
                                    placeholder="Radicado, nombres, documento o asunto..."
                                />
                            </div>
                        </div>

                        <!-- Tipo Entidad -->
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1 ml-1">Tipo de Entidad</label>
                            <select 
                                v-model="tipoEntidad"
                                class="block w-full py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-sm focus:ring-indigo-500 focus:border-indigo-500 dark:text-white"
                            >
                                <option value="">Todas las Entidades</option>
                                <option v-for="tipo in tiposEntidad" :key="tipo" :value="tipo">{{ tipo }}s</option>
                            </select>
                        </div>

                        <!-- Estado -->
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1 ml-1">Estado</label>
                            <select 
                                v-model="estado"
                                class="block w-full py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-sm focus:ring-indigo-500 focus:border-indigo-500 dark:text-white"
                            >
                                <option value="TODOS">Todos los Estados</option>
                                <option value="ACTIVO">Activos</option>
                                <option value="CERRADO">Cerrados</option>
                            </select>
                        </div>

                        <!-- Filtro Sin Radicado -->
                        <div class="flex items-center gap-3 bg-gray-50 dark:bg-gray-700 p-2.5 rounded-lg border border-gray-100 dark:border-gray-600 h-[38px] mt-5 self-start">
                            <label class="flex items-center gap-2 cursor-pointer w-full">
                                <input 
                                    v-model="sinRadicado"
                                    type="checkbox" 
                                    class="rounded text-indigo-600 focus:ring-indigo-500 border-gray-300 h-4 w-4"
                                />
                                <span class="text-[10px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-300">Sin Radicado</span>
                            </label>
                        </div>
                    </div>

                    <!-- Botón Limpiar y Resultados -->
                    <div class="flex justify-between items-center pt-2 border-t border-gray-50 dark:border-gray-700">
                        <div class="text-sm text-gray-500 dark:text-gray-400 font-medium">
                            Mostrando <span class="text-indigo-600 dark:text-indigo-400 font-bold">{{ procesos.total }}</span> expedientes
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

                <!-- Tabla de Datos (Desktop) -->
                <div class="bg-white dark:bg-gray-800 shadow-xl rounded-xl border border-gray-100 dark:border-gray-700 overflow-hidden hidden md:block">
                    <div class="overflow-x-auto custom-scrollbar-horizontal">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50/50 dark:bg-gray-700/50">
                                <tr>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-black text-gray-400 uppercase tracking-wider">
                                        Expediente / Asunto
                                    </th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-black text-gray-400 uppercase tracking-wider">
                                        Partes Involucradas
                                    </th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-black text-gray-400 uppercase tracking-wider">
                                        Responsables
                                    </th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-black text-gray-400 uppercase tracking-wider">
                                        Seguimiento
                                    </th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-black text-gray-400 uppercase tracking-wider">
                                        Etapa Actual
                                    </th>
                                    <th scope="col" class="sticky right-0 bg-gray-50 dark:bg-gray-700 px-6 py-4 text-right text-xs font-black text-gray-400 uppercase tracking-wider z-10 shadow-[-4px_0_6px_-2px_rgba(0,0,0,0.05)]">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700 bg-white dark:bg-gray-800">
                                <tr v-if="procesos.data.length === 0">
                                    <td colspan="6" class="px-6 py-20 text-center">
                                        <div class="flex flex-col items-center">
                                            <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-full mb-4">
                                                <InboxIcon class="h-10 w-10 text-gray-400" />
                                            </div>
                                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">No se encontraron expedientes</h3>
                                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-xs mx-auto">
                                                No hay registros que coincidan con los criterios de búsqueda aplicados.
                                            </p>
                                            <button @click="resetFilters" class="mt-4 text-indigo-600 font-bold hover:underline">Limpiar filtros</button>
                                        </div>
                                    </td>
                                </tr>
                                
                                <tr v-else v-for="proceso in procesos.data" :key="proceso.id" class="hover:bg-indigo-50/30 dark:hover:bg-indigo-900/10 transition-colors group cursor-pointer" @click="openQuickView(proceso)">
                                    <!-- Expediente / Asunto -->
                                    <td class="px-6 py-4 min-w-[250px]">
                                        <div class="flex flex-col">
                                            <div class="flex items-center gap-2">
                                                <Link :href="route('procesos.show', proceso.id)" class="text-sm font-black text-indigo-600 dark:text-indigo-400 hover:underline">
                                                    {{ proceso.radicado || 'SIN RADICADO' }}
                                                </Link>
                                                <button v-if="proceso.radicado" @click="copyToClipboard(proceso.radicado)" class="text-gray-300 hover:text-indigo-500 transition-colors">
                                                    <DocumentDuplicateIcon class="w-3.5 h-3.5" />
                                                </button>
                                                <span v-if="proceso.info_incompleta" class="text-[9px] font-black bg-red-100 text-red-700 px-1.5 py-0.5 rounded border border-red-200">FALTA INFO</span>
                                            </div>
                                            <p class="text-[11px] text-gray-500 dark:text-gray-400 font-medium line-clamp-2 mt-1" :title="proceso.asunto">
                                                {{ proceso.asunto || 'Sin asunto registrado' }}
                                            </p>
                                            <div class="mt-2 flex items-center gap-2">
                                                <span class="text-[9px] font-black text-gray-400 uppercase tracking-widest bg-gray-50 dark:bg-gray-700 px-1.5 py-0.5 rounded">{{ proceso.tipo_proceso?.nombre || 'General' }}</span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Partes -->
                                    <td class="px-6 py-4">
                                        <div class="space-y-2 max-w-[200px]">
                                            <div class="flex items-start gap-2">
                                                <span class="text-[9px] font-black text-blue-600 bg-blue-50 px-1 rounded border border-blue-100 shrink-0">DTE</span>
                                                <span class="text-[11px] font-bold text-gray-700 dark:text-gray-300 truncate" :title="formatNames(proceso.demandantes)">{{ formatNames(proceso.demandantes) }}</span>
                                            </div>
                                            <div class="flex items-start gap-2">
                                                <span class="text-[9px] font-black text-red-600 bg-red-50 px-1 rounded border border-red-100 shrink-0">DDO</span>
                                                <span class="text-[11px] font-bold text-gray-700 dark:text-gray-300 truncate" :title="formatNames(proceso.demandados)">{{ formatNames(proceso.demandados) }}</span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Responsables -->
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col gap-1.5">
                                            <div class="flex items-center gap-2">
                                                <div class="w-5 h-5 bg-indigo-500 rounded-full flex items-center justify-center text-[9px] text-white font-black" :title="'Abogado: ' + proceso.abogado?.name">{{ (proceso.abogado?.name || 'S')[0] }}</div>
                                                <span class="text-[11px] font-bold text-gray-600 dark:text-gray-400">{{ proceso.abogado?.name.split(' ')[0] || '—' }}</span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <div class="w-5 h-5 bg-amber-500 rounded-full flex items-center justify-center text-[9px] text-white font-black" :title="'Revisor: ' + proceso.responsable_revision?.name">{{ (proceso.responsable_revision?.name || 'S')[0] }}</div>
                                                <span class="text-[11px] font-bold text-gray-500 dark:text-gray-500">{{ proceso.responsable_revision?.name.split(' ')[0] || '—' }}</span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Seguimiento -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex flex-col gap-2">
                                            <div class="flex items-center gap-1.5 text-[10px] font-bold text-gray-400 uppercase tracking-tighter">
                                                <ArrowPathIcon class="w-3 h-3" />
                                                {{ formatDate(proceso.fecha_revision) || '--' }}
                                            </div>
                                            <div class="flex items-center gap-1.5 text-[10px] font-black px-2 py-1 rounded-lg w-fit border leading-none uppercase tracking-widest shadow-sm"
                                                :class="getRevisionStatus(proceso.fecha_proxima_revision)?.classes || ''">
                                                <CalendarDaysIcon class="h-3 w-3" />
                                                {{ formatDate(proceso.fecha_proxima_revision) || 'Pendiente' }}
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Etapa -->
                                    <td class="px-6 py-4">
                                        <div v-if="proceso.estado === 'CERRADO'" class="flex items-center gap-1.5">
                                            <span class="px-2 py-1 inline-flex text-[10px] font-black rounded-lg bg-gray-100 text-gray-500 border border-gray-200 uppercase tracking-widest">
                                                <CheckCircleIcon class="w-3 h-3 mr-1"/> Archivada
                                            </span>
                                        </div>
                                        <div v-else class="flex flex-col gap-1.5 items-start">
                                            <span class="px-2.5 py-1 text-[10px] font-black rounded-lg border leading-none uppercase tracking-wider shadow-sm" :class="getEtapaColor(proceso.etapa_actual?.riesgo)">
                                                {{ proceso.etapa_actual?.nombre || 'EN TRÁMITE' }}
                                            </span>
                                            <div v-if="getVencimientoInfo(proceso)" class="flex items-center gap-1 text-[9px] font-black uppercase tracking-tighter">
                                                <ClockIcon class="w-3 h-3" :class="getVencimientoInfo(proceso).class" />
                                                <span :class="getVencimientoInfo(proceso).class">{{ getVencimientoInfo(proceso).text }}</span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Acciones Sticky -->
                                    <td class="sticky right-0 bg-white dark:bg-gray-800 group-hover:bg-indigo-50/50 dark:group-hover:bg-gray-700 px-6 py-4 whitespace-nowrap text-right z-10 shadow-[-4px_0_6px_-2px_rgba(0,0,0,0.05)] transition-colors">
                                        <div class="flex items-center justify-end gap-2">
                                            <Link :href="route('procesos.show', proceso.id)" class="p-2 text-indigo-500 bg-indigo-50 dark:bg-indigo-900/30 rounded-lg hover:bg-indigo-600 hover:text-white transition-all shadow-sm">
                                                <EyeIcon class="w-5 h-5" />
                                            </Link>
                                            
                                            <Dropdown align="right" width="48">
                                                <template #trigger>
                                                    <button class="p-2 text-gray-400 hover:text-indigo-600 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-all">
                                                        <EllipsisVerticalIcon class="w-5 h-5" />
                                                    </button>
                                                </template>
                                                <template #content>
                                                    <DropdownLink :href="route('procesos.edit', proceso.id)">
                                                        <div class="flex items-center gap-2"><PencilSquareIcon class="w-4 h-4" /> Editar Información</div>
                                                    </DropdownLink>
                                                    <button v-if="proceso.estado === 'ACTIVO' && $page.props.auth.user.tipo_usuario === 'admin'" @click="openCloseModal(proceso)" class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 font-bold border-t dark:border-gray-700 mt-1">
                                                        <div class="flex items-center gap-2"><ArchiveBoxXMarkIcon class="w-4 h-4" /> Finalizar Proceso</div>
                                                    </button>
                                                    <button v-if="proceso.estado === 'CERRADO' && $page.props.auth.user.tipo_usuario === 'admin'" @click="openReopenModal(proceso)" class="block w-full text-left px-4 py-2 text-sm text-green-600 hover:bg-green-50 dark:hover:bg-green-900/20 font-bold border-t dark:border-gray-700 mt-1">
                                                        <div class="flex items-center gap-2"><ArrowPathIcon class="w-4 h-4" /> Reactivar Caso</div>
                                                    </button>
                                                </template>
                                            </Dropdown>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Móvil: Cards Modernas -->
                <div class="md:hidden space-y-4">
                    <div v-for="proceso in procesos.data" :key="'mob-'+proceso.id" @click="openQuickView(proceso)" class="bg-white dark:bg-gray-800 p-5 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 relative overflow-hidden cursor-pointer">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <Link :href="route('procesos.show', proceso.id)" class="text-lg font-black text-indigo-600 dark:text-indigo-400 block">{{ proceso.radicado || 'SIN RADICADO' }}</Link>
                                <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">{{ proceso.tipo_proceso?.nombre || 'General' }}</span>
                            </div>
                            <span class="px-2 py-1 text-[9px] font-black rounded-lg border uppercase tracking-tighter shadow-sm" :class="getEtapaColor(proceso.etapa_actual?.riesgo)">
                                {{ proceso.etapa_actual?.nombre || proceso.estado }}
                            </span>
                        </div>

                        <div class="space-y-3 mb-6">
                            <div class="flex items-start gap-2">
                                <span class="text-[9px] font-black text-blue-600 bg-blue-50 px-1 rounded border border-blue-100 mt-0.5">DTE</span>
                                <p class="text-xs font-bold text-gray-700 dark:text-gray-300 leading-tight">{{ formatNames(proceso.demandantes) }}</p>
                            </div>
                            <div class="flex items-start gap-2">
                                <span class="text-[9px] font-black text-red-600 bg-red-50 px-1 rounded border border-red-100 mt-0.5">DDO</span>
                                <p class="text-xs font-bold text-gray-700 dark:text-gray-300 leading-tight">{{ formatNames(proceso.demandados) }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3 pt-4 border-t border-gray-50 dark:border-gray-700">
                            <Link :href="route('procesos.edit', proceso.id)" class="flex items-center justify-center py-2 bg-gray-50 dark:bg-gray-700 text-[10px] font-black uppercase rounded-xl text-gray-500">Editar</Link>
                            <Link :href="route('procesos.show', proceso.id)" class="flex items-center justify-center py-2 bg-indigo-600 text-[10px] font-black uppercase rounded-xl text-white shadow-lg shadow-indigo-100">Ver Ficha</Link>
                        </div>
                    </div>
                </div>

                <!-- Paginación -->
                <div v-if="procesos.links.length > 3" class="mt-8 flex justify-center bg-white dark:bg-gray-800 p-4 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm">
                    <Pagination :links="procesos.links" />
                </div>
            </div>
        </div>

        <!-- MODALES REDISEÑADOS -->
        <Modal :show="showCloseModal" @close="showCloseModal = false">
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

        <Modal :show="showReopenModal" @close="showReopenModal = false">
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

        <!-- MODAL DE VISTA RÁPIDA (V5: Ficha Judicial Optimizada) -->
        <Modal :show="showQuickViewModal" @close="closeQuickView" max-width="3xl">
            <div v-if="selectedProceso" class="overflow-hidden rounded-2xl bg-white dark:bg-gray-900 shadow-2xl transition-all border border-gray-100 dark:border-gray-800 flex flex-col h-[85vh] sm:h-[90vh]">
                <!-- Header -->
                <div class="px-4 py-3 sm:px-8 sm:py-5 bg-indigo-600 dark:bg-indigo-700 text-white flex justify-between items-center shrink-0 shadow-lg relative z-10 w-full">
                    <div class="flex items-center gap-3 sm:gap-4 overflow-hidden min-w-0">
                        <div class="hidden sm:flex h-10 w-10 bg-white/10 rounded-xl items-center justify-center border border-white/20 shrink-0">
                            <ScaleIcon class="w-6 h-6" />
                        </div>
                        <div class="truncate min-w-0">
                            <p class="text-[9px] sm:text-[10px] font-black uppercase tracking-widest text-indigo-100/70 truncate">Expediente #{{ selectedProceso.id }}</p>
                            <h2 class="text-base sm:text-lg font-black leading-tight truncate">{{ selectedProceso.radicado || 'SIN RADICADO' }}</h2>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 sm:gap-4 shrink-0 ml-2">
                        <div class="flex bg-black/10 rounded-lg p-0.5">
                            <button @click="prevProceso" :disabled="currentIndex === 0" class="p-1 sm:p-1.5 hover:bg-white/10 disabled:opacity-20 rounded-md transition-all active:scale-90"><ChevronDownIcon class="w-4 h-4 rotate-90" /></button>
                            <div class="px-1.5 sm:px-3 flex items-center border-x border-white/5">
                                <span class="text-[9px] sm:text-[10px] font-black tracking-widest">{{ currentIndex + 1 }}<span class="opacity-50 mx-0.5">/</span>{{ procesos.data.length }}</span>
                            </div>
                            <button @click="nextProceso" :disabled="currentIndex === procesos.data.length - 1" class="p-1 sm:p-1.5 hover:bg-white/10 disabled:opacity-20 rounded-md transition-all active:scale-90"><ChevronDownIcon class="w-4 h-4 -rotate-90" /></button>
                        </div>
                        <button @click="closeQuickView" class="p-1.5 sm:p-2 hover:bg-white/10 rounded-lg transition-colors"><XMarkIcon class="w-5 h-5" /></button>
                    </div>
                </div>

                <!-- Cuerpo -->
                <div class="flex-grow overflow-y-auto p-4 sm:p-8 custom-scrollbar space-y-8 sm:space-y-10 bg-gray-50/30 dark:bg-gray-900/50 w-full">
                    
                    <!-- 1. PARTES PROCESALES -->
                    <section class="space-y-4">
                        <div class="flex items-center gap-2 border-b border-gray-100 dark:border-gray-800 pb-2">
                            <UserGroupIcon class="w-4 h-4 text-indigo-500" />
                            <h3 class="text-[10px] sm:text-xs font-black text-gray-900 dark:text-white uppercase tracking-widest">Sujetos Procesales</h3>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6 bg-white dark:bg-gray-800 p-4 sm:p-6 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm">
                            <div class="space-y-4 min-w-0">
                                <div>
                                    <p class="text-[9px] font-black text-blue-600 uppercase tracking-widest mb-1.5">Demandantes (DTE)</p>
                                    <div class="space-y-2">
                                        <div v-for="p in selectedProceso.demandantes" :key="p.id" class="flex items-start gap-2">
                                            <div class="w-1.5 h-1.5 rounded-full bg-blue-400 mt-1.5 shrink-0"></div>
                                            <span class="text-xs font-bold text-gray-700 dark:text-gray-300 break-words">{{ p.nombre_completo }}</span>
                                        </div>
                                        <span v-if="!selectedProceso.demandantes?.length" class="text-xs italic text-gray-400">No registrados</span>
                                    </div>
                                </div>
                            </div>
                            <div class="space-y-4 pt-4 md:pt-0 border-t md:border-t-0 border-gray-100 dark:border-gray-700 min-w-0">
                                <div>
                                    <p class="text-[9px] font-black text-red-600 uppercase tracking-widest mb-1.5">Demandados (DDO)</p>
                                    <div class="space-y-2">
                                        <div v-for="p in selectedProceso.demandados" :key="p.id" class="flex items-start gap-2">
                                            <div class="w-1.5 h-1.5 rounded-full bg-red-400 mt-1.5 shrink-0"></div>
                                            <span class="text-xs font-bold text-gray-700 dark:text-gray-300 break-words">{{ p.nombre_completo }}</span>
                                        </div>
                                        <span v-if="!selectedProceso.demandados?.length" class="text-xs italic text-gray-400">No registrados</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- 2. DATOS DEL ASUNTO Y JUZGADO -->
                    <section class="space-y-4">
                        <div class="flex items-center gap-2 border-b border-gray-100 dark:border-gray-800 pb-2">
                            <BuildingLibraryIcon class="w-4 h-4 text-indigo-500" />
                            <h3 class="text-[10px] sm:text-xs font-black text-gray-900 dark:text-white uppercase tracking-widest">Información del Despacho</h3>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 px-1">
                            <div class="sm:col-span-2 lg:col-span-3 space-y-1.5 bg-indigo-50/50 dark:bg-indigo-900/10 p-4 rounded-xl border border-indigo-100/50 dark:border-indigo-800/30">
                                <p class="text-[9px] font-bold text-indigo-600 uppercase">Asunto del Proceso</p>
                                <p class="text-xs font-black text-gray-800 dark:text-gray-200 leading-relaxed break-words">{{ selectedProceso.asunto || 'Sin asunto registrado' }}</p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-[9px] font-bold text-gray-400 uppercase">Tipo de Proceso</p>
                                <p class="text-xs font-black text-gray-800 dark:text-gray-200">{{ selectedProceso.tipo_proceso?.nombre || 'General' }}</p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-[9px] font-bold text-gray-400 uppercase">Clase / Subproceso</p>
                                <p class="text-xs font-black text-gray-800 dark:text-gray-200 uppercase">{{ selectedProceso.clase_proceso || 'N/A' }}</p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-[9px] font-bold text-gray-400 uppercase">Estado Actual</p>
                                <p class="text-xs font-black text-indigo-600 dark:text-indigo-400 uppercase">{{ selectedProceso.estado }}</p>
                            </div>
                            <div class="sm:col-span-2 lg:col-span-3 space-y-1 bg-gray-50 dark:bg-gray-800/50 p-3.5 rounded-xl border border-gray-100 dark:border-gray-700">
                                <p class="text-[9px] font-bold text-gray-400 uppercase">Despacho Judicial</p>
                                <p class="text-xs font-bold text-gray-800 dark:text-gray-200 flex items-center gap-2 break-words">
                                    <BuildingOfficeIcon class="w-3.5 h-3.5 text-gray-400 shrink-0" />
                                    {{ selectedProceso.entidad || 'Por definir' }}
                                </p>
                            </div>
                        </div>
                    </section>

                    <!-- 3. SEGUIMIENTO Y FECHAS -->
                    <section class="space-y-4">
                        <div class="flex items-center gap-2 border-b border-gray-100 dark:border-gray-800 pb-2">
                            <CalendarDaysIcon class="w-4 h-4 text-indigo-500" />
                            <h3 class="text-[10px] sm:text-xs font-black text-gray-900 dark:text-white uppercase tracking-widest">Cronograma de Revisión</h3>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="bg-white dark:bg-gray-800 p-4 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm flex items-center gap-4">
                                <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-xl"><ClockIcon class="w-5 h-5 text-gray-400" /></div>
                                <div>
                                    <p class="text-[9px] font-black text-gray-400 uppercase mb-0.5">Última Revisión</p>
                                    <p class="text-xs font-black text-gray-800 dark:text-gray-200">{{ formatDate(selectedProceso.fecha_revision) || 'Sin registro' }}</p>
                                </div>
                            </div>
                            <div class="bg-white dark:bg-gray-800 p-4 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm flex items-center gap-4" :class="getRevisionStatus(selectedProceso.fecha_proxima_revision)?.classes">
                                <div class="p-3 bg-white/20 rounded-xl"><CalendarDaysIcon class="w-5 h-5" /></div>
                                <div>
                                    <p class="text-[9px] font-black uppercase mb-0.5 opacity-70">Próxima Revisión</p>
                                    <p class="text-xs font-black">{{ formatDate(selectedProceso.fecha_proxima_revision) || 'Pendiente programar' }}</p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- 4. RESPONSABLES -->
                    <section class="space-y-4">
                        <div class="flex items-center gap-2 border-b border-gray-100 dark:border-gray-800 pb-2">
                            <ClipboardDocumentListIcon class="w-4 h-4 text-indigo-500" />
                            <h3 class="text-[10px] sm:text-xs font-black text-gray-900 dark:text-white uppercase tracking-widest">Responsables del Caso</h3>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 px-1">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-indigo-100 dark:bg-indigo-900/50 rounded-full flex items-center justify-center text-indigo-700 dark:text-indigo-300 font-black text-xs">{{ (selectedProceso.abogado?.name || 'S')[0] }}</div>
                                <div>
                                    <p class="text-[9px] font-bold text-gray-400 uppercase">Abogado Titular</p>
                                    <p class="text-xs font-black text-gray-700 dark:text-gray-300">{{ selectedProceso.abogado?.name }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-amber-100 dark:bg-amber-900/50 rounded-full flex items-center justify-center text-amber-700 dark:text-amber-300 font-black text-xs">{{ (selectedProceso.responsable_revision?.name || 'S')[0] }}</div>
                                <div>
                                    <p class="text-[9px] font-bold text-gray-400 uppercase">Responsable Revisión</p>
                                    <p class="text-xs font-black text-gray-700 dark:text-gray-300">{{ selectedProceso.responsable_revision?.name || 'No asignado' }}</p>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- Footer -->
                <div class="px-4 py-4 sm:px-8 sm:py-5 bg-white dark:bg-gray-800 border-t border-gray-100 dark:border-gray-700 flex flex-col sm:flex-row justify-between items-center gap-4 shrink-0 shadow-[0_-4px_10px_rgba(0,0,0,0.03)] relative z-10">
                    <p class="text-[10px] font-bold text-gray-400 italic flex items-center gap-2">
                        <ArrowPathIcon class="w-3.5 h-3.5" /> 
                        Estado del expediente: <span class="font-black uppercase tracking-widest" :class="selectedProceso.estado === 'ACTIVO' ? 'text-green-500' : 'text-gray-500'">{{ selectedProceso.estado }}</span>
                    </p>
                    <Link :href="route('procesos.show', selectedProceso.id)" class="w-full sm:w-auto px-8 py-3.5 bg-gray-900 dark:bg-white text-white dark:text-gray-900 rounded-2xl font-black text-[11px] uppercase tracking-[0.2em] transition-all hover:bg-black dark:hover:bg-gray-100 shadow-xl shadow-gray-200 dark:shadow-none active:scale-[0.98] text-center">
                        Ingresar al Expediente Judicial &rarr;
                    </Link>
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