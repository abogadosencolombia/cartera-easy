<script setup>
import { ref, watch } from 'vue';
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
    BuildingLibraryIcon 
} from '@heroicons/vue/24/outline';

const props = defineProps({
    procesos: { type: Object, required: true },
    filtros: { type: Object, default: () => ({}) },
    // Eliminamos 'juzgados' porque ahora usamos tipos fijos
});

const { getRevisionStatus } = useProcesos();

// --- TIPOS DE ENTIDAD (Hardcoded para facilitar uso sin DB extra) ---
const tiposEntidad = [
    'Juzgado',
    'Fiscalía',
    'Secretaría',
    'Despacho',
    'Centro de Servicios',
    'Corte',
    'Tribunal',
    'Notaría',
    'Superintendencia'
];

// --- FILTROS ---
const search = ref(props.filtros.search || '');
const estado = ref(props.filtros.estado || 'TODOS');
const tipoEntidad = ref(props.filtros.tipo_entidad || ''); // <--- NUEVO FILTRO POR TIPO

const applyFilters = debounce(() => {
    router.get(route('procesos.index'), { 
        search: search.value, 
        estado: estado.value === 'TODOS' ? '' : estado.value,
        tipo_entidad: tipoEntidad.value // <--- ENVIAR TIPO
    }, { preserveState: true, replace: true });
}, 300);

watch([search, estado, tipoEntidad], applyFilters);

// --- EXPORTAR ---
const exportarExcel = () => {
    const params = new URLSearchParams({
        search: search.value || '',
        estado: estado.value === 'TODOS' ? '' : estado.value,
        tipo_entidad: tipoEntidad.value || '', // <--- INCLUIR EN EXPORTACIÓN
    });
    window.location.href = route('procesos.exportar') + '?' + params.toString();
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

// --- HELPER PARA NOMBRES ---
const formatNames = (personas) => {
    if (!personas || personas.length === 0) return 'Sin asignar';
    if (personas.length <= 2) return personas.map(p => p.nombre_completo).join(', ');
    return `${personas[0].nombre_completo}, ${personas[1].nombre_completo} y ${personas.length - 2} más`;
};

// --- FUNCIÓN DE FECHAS SEGURA ---
const formatDate = (dateString) => {
    if (!dateString) return null;
    try {
        const date = dateString.length === 10 
            ? new Date(dateString + 'T00:00:00') 
            : new Date(dateString);
            
        if (isNaN(date.getTime())) return dateString;

        return new Intl.DateTimeFormat('es-CO', { day: 'numeric', month: 'short', year: 'numeric' }).format(date);
    } catch (e) {
        console.error("Error formateando fecha:", e);
        return dateString; 
    }
};

const getRiesgoClasses = (proceso) => {
    if (proceso.estado === 'CERRADO') {
        return 'bg-gray-100 text-gray-500 border-gray-200 dark:bg-gray-700 dark:text-gray-400';
    }
    if (!proceso.etapa_actual) {
        return 'bg-blue-50 text-blue-700 border-blue-100 dark:bg-blue-900/20 dark:text-blue-300';
    }
    switch (proceso.etapa_actual.riesgo) {
        case 'MUY_ALTO': return 'bg-red-50 text-red-700 border-red-200 dark:bg-red-900/20 dark:text-red-300';
        case 'ALTO': return 'bg-orange-50 text-orange-700 border-orange-200 dark:bg-orange-900/20 dark:text-orange-300';
        case 'MEDIO': return 'bg-yellow-50 text-yellow-700 border-yellow-200 dark:bg-yellow-900/20 dark:text-yellow-300';
        default: return 'bg-green-50 text-green-700 border-green-200 dark:bg-green-900/20 dark:text-green-300';
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
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div>
                    <h2 class="font-bold text-2xl text-gray-800 dark:text-gray-100 leading-tight flex items-center gap-2">
                        <ScaleIcon class="h-8 w-8 text-indigo-600 dark:text-indigo-400" />
                        Expedientes Judiciales
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Gestión y seguimiento de procesos radicados.</p>
                </div>
                <div class="flex items-center gap-3 w-full sm:w-auto">
                    <SecondaryButton @click="exportarExcel" class="justify-center w-full sm:w-auto">
                        <DocumentArrowDownIcon class="h-4 w-4 mr-2" />
                        Exportar
                    </SecondaryButton>
                    <Link :href="route('procesos.create')" class="w-full sm:w-auto">
                        <PrimaryButton class="justify-center w-full sm:w-auto">
                            <PlusIcon class="h-4 w-4 mr-2" />
                            Nuevo Radicado
                        </PrimaryButton>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-8xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <!-- BARRA DE HERRAMIENTAS -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4">
                    <div class="flex flex-col xl:flex-row gap-4 justify-between">
                        
                        <!-- Buscador -->
                        <div class="relative flex-grow max-w-2xl">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" />
                            </div>
                            <TextInput 
                                v-model="search" 
                                type="text" 
                                class="pl-10 w-full bg-gray-50 dark:bg-gray-900 border-gray-300 dark:border-gray-600 focus:ring-indigo-500 focus:border-indigo-500" 
                                placeholder="Buscar por radicado, nombres, cédula/NIT, asunto..." 
                            />
                        </div>
                        
                        <!-- Filtros (Estado y Tipo de Entidad) -->
                        <div class="flex flex-col sm:flex-row items-center gap-3 w-full xl:w-auto">
                            
                            <!-- Filtro Tipo de Entidad -->
                            <div class="w-full sm:w-64 flex items-center gap-2 bg-gray-50 dark:bg-gray-900 px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600">
                                <BuildingLibraryIcon class="h-4 w-4 text-gray-500 flex-shrink-0" />
                                <select 
                                    v-model="tipoEntidad" 
                                    class="bg-transparent border-none text-sm text-gray-700 dark:text-gray-300 focus:ring-0 p-0 cursor-pointer w-full truncate"
                                >
                                    <option value="">Todas las Entidades</option>
                                    <option v-for="tipo in tiposEntidad" :key="tipo" :value="tipo">
                                        {{ tipo }}s
                                    </option>
                                </select>
                            </div>

                            <!-- Filtro Estado -->
                            <div class="w-full sm:w-auto flex items-center gap-2 bg-gray-50 dark:bg-gray-900 px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 shrink-0">
                                <FunnelIcon class="h-4 w-4 text-gray-500" />
                                <select 
                                    v-model="estado" 
                                    class="bg-transparent border-none text-sm text-gray-700 dark:text-gray-300 focus:ring-0 p-0 cursor-pointer"
                                >
                                    <option value="TODOS">Todos los Estados</option>
                                    <option value="ACTIVO">Activos</option>
                                    <option value="CERRADO">Cerrados</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TABLA DE DATOS (DESKTOP) -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden hidden md:block">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700/50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Radicado / Asunto</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Partes</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Juzgado/Entidad</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Seguimiento</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Etapa Procesal</th>
                                    <th scope="col" class="relative px-6 py-3"><span class="sr-only">Acciones</span></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="proceso in procesos.data" :key="proceso.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors group">
                                    
                                    <!-- Radicado -->
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col">
                                            <Link :href="route('procesos.show', proceso.id)" class="text-sm font-bold text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 mb-1">
                                                {{ proceso.radicado || 'Sin Radicado' }}
                                            </Link>
                                            <span class="text-xs text-gray-500 dark:text-gray-400 line-clamp-2 max-w-xs" :title="proceso.asunto">
                                                {{ proceso.asunto || 'Sin asunto registrado' }}
                                            </span>
                                            <span class="mt-1 inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300 w-fit">
                                                {{ proceso.tipo_proceso?.nombre || 'Tipo N/A' }}
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Partes -->
                                    <td class="px-6 py-4">
                                        <div class="space-y-2">
                                            <div class="flex items-start gap-2">
                                                <span class="bg-blue-100 text-blue-700 text-[10px] font-bold px-1.5 py-0.5 rounded border border-blue-200 shrink-0">DTE</span>
                                                <span class="text-xs text-gray-700 dark:text-gray-300 line-clamp-2">
                                                    {{ formatNames(proceso.demandantes) }}
                                                </span>
                                            </div>
                                            <div class="flex items-start gap-2">
                                                <span class="bg-red-100 text-red-700 text-[10px] font-bold px-1.5 py-0.5 rounded border border-red-200 shrink-0">DDO</span>
                                                <span class="text-xs text-gray-700 dark:text-gray-300 line-clamp-2">
                                                    {{ formatNames(proceso.demandados) }}
                                                </span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Juzgado -->
                                    <td class="px-6 py-4">
                                        <div class="flex items-start gap-2 text-sm text-gray-600 dark:text-gray-400">
                                            <MapPinIcon class="h-4 w-4 mt-0.5 text-gray-400 shrink-0" />
                                            <span class="line-clamp-2 text-xs">{{ proceso.juzgado?.nombre || 'Sin asignar' }}</span>
                                        </div>
                                    </td>

                                    <!-- SEGUIMIENTO -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex flex-col gap-2">
                                            <!-- Última revisión -->
                                            <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400" title="Última revisión realizada">
                                                <ArrowPathIcon class="h-3.5 w-3.5 text-gray-400" />
                                                <span>{{ formatDate(proceso.fecha_revision) || '--' }}</span>
                                            </div>
                                            <!-- Próxima revisión -->
                                            <div class="flex items-center gap-1.5 text-xs font-semibold px-2 py-1 rounded-full w-fit border"
                                                :class="getRevisionStatus(proceso.fecha_proxima_revision)?.classes || ''">
                                                <CalendarDaysIcon class="h-3.5 w-3.5" />
                                                {{ formatDate(proceso.fecha_proxima_revision) || 'Pendiente' }}
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Etapa Procesal -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div v-if="proceso.estado === 'CERRADO'" class="flex items-center gap-2">
                                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                                <CheckCircleIcon class="w-3.5 h-3.5 mr-1"/> Cerrado
                                            </span>
                                        </div>
                                        <div v-else class="flex flex-col gap-1">
                                            <div class="flex items-center px-2.5 py-1 rounded-md border w-fit" :class="getRiesgoClasses(proceso)">
                                                <ExclamationTriangleIcon v-if="proceso.etapa_actual?.riesgo === 'MUY_ALTO' || proceso.etapa_actual?.riesgo === 'ALTO'" class="w-3.5 h-3.5 mr-1.5" />
                                                <span class="text-xs font-bold uppercase tracking-wide">
                                                    {{ proceso.etapa_actual?.nombre || 'EN TRÁMITE' }}
                                                </span>
                                            </div>
                                            
                                            <!-- SLA -->
                                            <div v-if="getVencimientoInfo(proceso)" class="flex items-center gap-1 text-[11px] mt-0.5 ml-1">
                                                <ClockIcon class="w-3 h-3" :class="getVencimientoInfo(proceso).class" />
                                                <span :class="getVencimientoInfo(proceso).class">
                                                    {{ getVencimientoInfo(proceso).text }}
                                                </span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Acciones -->
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                            <Link :href="route('procesos.show', proceso.id)" class="text-indigo-600 hover:text-indigo-900 bg-indigo-50 p-1.5 rounded-md hover:bg-indigo-100">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                </svg>
                                            </Link>
                                            <Dropdown align="right" width="48">
                                                <template #trigger>
                                                    <button class="text-gray-400 hover:text-gray-600 p-1.5 rounded-md hover:bg-gray-100">
                                                        <EllipsisVerticalIcon class="h-5 w-5" />
                                                    </button>
                                                </template>
                                                <template #content>
                                                    <DropdownLink :href="route('procesos.edit', proceso.id)">Editar Información</DropdownLink>
                                                    <button v-if="proceso.estado === 'ACTIVO' && $page.props.auth.user.tipo_usuario === 'admin'" @click="openCloseModal(proceso)" class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100">Cerrar Caso</button>
                                                    <button v-if="proceso.estado === 'CERRADO' && $page.props.auth.user.tipo_usuario === 'admin'" @click="openReopenModal(proceso)" class="block w-full text-left px-4 py-2 text-sm text-green-600 hover:bg-gray-100">Reabrir Caso</button>
                                                </template>
                                            </Dropdown>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- LISTA PARA MÓVIL (Cards) -->
                <div class="md:hidden space-y-4">
                    <div v-if="procesos.data.length === 0" class="text-center py-10 text-gray-500 bg-white rounded-lg shadow">
                        No se encontraron procesos.
                    </div>
                    
                    <div v-for="proceso in procesos.data" :key="proceso.id" class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow border-l-4 relative overflow-hidden" 
                        :class="proceso.estado === 'CERRADO' ? 'border-l-gray-400 opacity-75' : (proceso.etapa_actual?.riesgo === 'MUY_ALTO' ? 'border-l-red-500' : 'border-l-indigo-500')">
                        
                        <div class="flex justify-between items-start mb-3">
                            <div>
                                <h3 class="font-bold text-indigo-600 dark:text-indigo-400 text-lg">{{ proceso.radicado }}</h3>
                                <p class="text-xs text-gray-500">{{ proceso.tipo_proceso?.nombre }}</p>
                            </div>
                            <span class="px-2 py-1 text-[10px] font-bold rounded-full border" :class="getRiesgoClasses(proceso)">
                                {{ proceso.etapa_actual?.nombre || proceso.estado }}
                            </span>
                        </div>
                        
                        <!-- Fechas en Móvil -->
                        <div class="grid grid-cols-2 gap-2 mb-3">
                            <div class="flex flex-col p-2 bg-gray-50 dark:bg-gray-700/30 rounded">
                                <span class="text-[10px] text-gray-500 uppercase font-bold">Última Rev.</span>
                                <span class="text-xs text-gray-700 dark:text-gray-300 font-medium flex items-center gap-1">
                                    <ArrowPathIcon class="w-3 h-3"/> {{ formatDate(proceso.fecha_revision) || '--' }}
                                </span>
                            </div>
                            <div class="flex flex-col p-2 rounded border" :class="getRevisionStatus(proceso.fecha_proxima_revision)?.classes || ''">
                                <span class="text-[10px] uppercase font-bold opacity-80">Próxima Rev.</span>
                                <span class="text-xs font-bold flex items-center gap-1">
                                    <CalendarDaysIcon class="w-3 h-3"/> {{ formatDate(proceso.fecha_proxima_revision) || 'Pendiente' }}
                                </span>
                            </div>
                        </div>

                        <!-- Alerta Vencimiento -->
                        <div v-if="getVencimientoInfo(proceso)" class="mb-3 flex items-center gap-1.5 p-2 rounded bg-red-50 dark:bg-red-900/20 border border-red-100 dark:border-red-900/30">
                            <ClockIcon class="w-4 h-4" :class="getVencimientoInfo(proceso).class" />
                            <span class="text-xs font-bold" :class="getVencimientoInfo(proceso).class">
                                {{ getVencimientoInfo(proceso).text }}
                            </span>
                        </div>

                        <div class="space-y-2 text-sm text-gray-700 dark:text-gray-300 mb-4">
                            <div class="flex gap-2">
                                <span class="font-bold text-xs w-8">Dte:</span>
                                <span class="text-xs flex-1">{{ formatNames(proceso.demandantes) }}</span>
                            </div>
                            <div class="flex gap-2">
                                <span class="font-bold text-xs w-8">Ddo:</span>
                                <span class="text-xs flex-1">{{ formatNames(proceso.demandados) }}</span>
                            </div>
                            <div class="flex gap-2" v-if="proceso.juzgado">
                                <span class="font-bold text-xs w-8">Ent:</span>
                                <span class="text-xs flex-1 text-gray-500">{{ proceso.juzgado.nombre }}</span>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                            <Link :href="route('procesos.edit', proceso.id)" class="text-gray-500 text-sm font-medium">Editar</Link>
                            <Link :href="route('procesos.show', proceso.id)" class="text-indigo-600 text-sm font-medium">Ver Detalle</Link>
                        </div>
                    </div>
                </div>

                <!-- PAGINACIÓN -->
                <div v-if="procesos.links.length > 3" class="mt-6">
                    <Pagination :links="procesos.links" />
                </div>
            </div>
        </div>

        <!-- MODALES -->
        <Modal :show="showCloseModal" @close="showCloseModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Cerrar Expediente</h2>
                <p class="text-sm text-gray-500 mb-4">Indica el motivo de cierre para el radicado <span class="font-mono font-bold">{{ procesoToManage?.radicado }}</span>.</p>
                <Textarea v-model="closeForm.nota_cierre" class="w-full" rows="3" placeholder="Ej: Sentencia final a favor..." />
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="showCloseModal = false">Cancelar</SecondaryButton>
                    <DangerButton @click="submitCloseCase" :disabled="closeForm.processing">Confirmar Cierre</DangerButton>
                </div>
            </div>
        </Modal>

        <Modal :show="showReopenModal" @close="showReopenModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Reabrir Expediente</h2>
                <p class="text-sm text-gray-500 mt-2">¿Confirmas que deseas reactivar este proceso?</p>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="showReopenModal = false">Cancelar</SecondaryButton>
                    <PrimaryButton @click="submitReopenCase" :disabled="reopenForm.processing">Sí, Reactivar</PrimaryButton>
                </div>
            </div>
        </Modal>

    </AuthenticatedLayout>
</template>