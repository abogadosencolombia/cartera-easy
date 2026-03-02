<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { debounce } from 'lodash-es';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import DatePicker from '@/Components/DatePicker.vue';
import {
    CheckIcon,
    FolderIcon,
    DocumentTextIcon,
    ClipboardDocumentCheckIcon,
    CheckCircleIcon,
    MagnifyingGlassIcon,
    PencilSquareIcon,
    ArrowDownTrayIcon, // --- AÑADIDO: Icono para exportar ---
    ChevronDownIcon
} from '@heroicons/vue/24/outline';
import Dropdown from '@/Components/Dropdown.vue';

// --- PROPS ---
const props = defineProps([
    'casos',
    'radicados',
    'contratos',
    'pendientesCounts',
    'filters',
    'abogados' // <-- AÑADIDO: Lista de abogados
]);

// --- STATE ---
const activeTab = ref(props.filters.active_tab || 'casos');
const searchCasos = ref(props.filters.search_casos || '');
const searchRadicados = ref(props.filters.search_radicados || '');
const searchContratos = ref(props.filters.search_contratos || '');
const startDate = ref(props.filters.start_date || '');
const endDate = ref(props.filters.end_date || '');
const abogadoId = ref(props.filters.abogado_id || '');
const isLoading = ref(false);

// --- COMPUTED ---

// Ordena las listas (no revisados arriba, revisados abajo)
const sortedCasos = computed(() =>
    props.casos?.data ? [...props.casos.data].sort((a, b) => (a.revisadoHoy ? 1 : 0) - (b.revisadoHoy ? 1 : 0)) : []
);
const sortedRadicados = computed(() =>
    props.radicados?.data ? [...props.radicados.data].sort((a, b) => (a.revisadoHoy ? 1 : 0) - (b.revisadoHoy ? 1 : 0)) : []
);
const sortedContratos = computed(() =>
    props.contratos?.data ? [...props.contratos.data].sort((a, b) => (a.revisadoHoy ? 1 : 0) - (b.revisadoHoy ? 1 : 0)) : []
);

// Cálculo de progreso (sin cambios)
const progresoActual = computed(() => {
    const defaultValue = { porcentaje: 0, texto: '0 / 0', totalItems: 0 };
    if (!props.casos || !props.radicados || !props.contratos || !props.pendientesCounts) return defaultValue;
    let total = 0; let pendientes = 0;
    try {
        if (activeTab.value === 'casos') {
             if (typeof props.casos.total === 'undefined' || typeof props.pendientesCounts.casos === 'undefined') return defaultValue;
             total = props.casos.total; pendientes = props.pendientesCounts.casos;
        } else if (activeTab.value === 'radicados') {
             if (typeof props.radicados.total === 'undefined' || typeof props.pendientesCounts.radicados === 'undefined') return defaultValue;
             total = props.radicados.total; pendientes = props.pendientesCounts.radicados;
        } else if (activeTab.value === 'contratos') {
             if (typeof props.contratos.total === 'undefined' || typeof props.pendientesCounts.contratos === 'undefined') return defaultValue;
             total = props.contratos.total; pendientes = props.pendientesCounts.contratos;
        } else { return defaultValue; }
    } catch (e) { console.error("Error al calcular el progreso:", e); return defaultValue; }
    if (total === 0 || typeof total === 'undefined' || typeof pendientes === 'undefined') return { porcentaje: 0, texto: '0 / 0', totalItems: 0 };
    if (pendientes < 0) pendientes = 0; if (pendientes > total) pendientes = total;
    const revisados = total - pendientes;
    const porcentaje = total > 0 ? Math.round((revisados / total) * 100) : 0;
    return { porcentaje, texto: `${revisados} / ${total}`, totalItems: total };
});

// --- INICIO: URL de Exportación Computada (MODIFICADA) ---
const exportUrl = computed(() => {
    const params = new URLSearchParams();
    params.append('active_tab', activeTab.value); // Pasar la pestaña activa

    // Añadir filtros de búsqueda si existen
    if (searchCasos.value) params.append('search_casos', searchCasos.value);
    if (searchRadicados.value) params.append('search_radicados', searchRadicados.value);
    if (searchContratos.value) params.append('search_contratos', searchContratos.value);

    // Añadir filtros de fecha si existen
    if (startDate.value) params.append('start_date', startDate.value);
    if (endDate.value) params.append('end_date', endDate.value);

    // --- AÑADIDO: Añadir filtro de abogado ---
    if (abogadoId.value) params.append('abogado_id', abogadoId.value);
    // --- FIN AÑADIDO ---

    return route('revision.export.pendientes') + '?' + params.toString();
});
// --- FIN: URL de Exportación Computada ---


// --- METHODS ---
const toggleRevision = (item, tipo) => {
    const onlyKey = (tipo === 'ProcesoRadicado' ? 'radicados' : tipo.toLowerCase() + 's');
    
    router.post(route('revision.toggle'), {
        id: item.id,
        tipo: tipo,
    }, {
        preserveState: true,
        preserveScroll: true,
        only: [onlyKey, 'pendientesCounts'],
    });
};

// --- RELOAD (Optimizado) ---
const reload = (resetPages = false) => {
    isLoading.value = true;
    
    const params = {
        'active_tab': activeTab.value,
        'search_casos': searchCasos.value,
        'search_radicados': searchRadicados.value,
        'search_contratos': searchContratos.value,
        'start_date': startDate.value,
        'end_date': endDate.value,
        'abogado_id': abogadoId.value,
    };

    // Si NO estamos reseteando páginas (ej. por auto-refresh),
    // intentamos mantener las páginas actuales del servidor.
    if (!resetPages) {
        if (props.casos?.current_page) params.casos_page = props.casos.current_page;
        if (props.radicados?.current_page) params.radicados_page = props.radicados.current_page;
        if (props.contratos?.current_page) params.contratos_page = props.contratos.current_page;
    }

    router.get(route('revision.index'), params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['casos', 'radicados', 'contratos', 'filters', 'pendientesCounts'],
        onFinish: () => { isLoading.value = false; }
    });
};

const debouncedReload = debounce(() => reload(true), 400);

// Ya no necesitamos vigilar activeTab porque lo manejaremos en el click de los botones
// watch(activeTab, (newTab) => {
//     reload();
// });

watch([searchCasos, searchRadicados, searchContratos, startDate, endDate, abogadoId], debouncedReload);

// --- AUTO-REFRESH AL VOLVER ---
// Esta lógica fuerza a Inertia a pedir los datos frescos del servidor
// cuando el usuario regresa a esta pestaña del navegador tras revisar un caso.
const handleVisibilityChange = () => {
    if (document.visibilityState === 'visible') {
        reload(false); // Mantener la página en el auto-refresh
    }
};

onMounted(() => {
    document.addEventListener('visibilitychange', handleVisibilityChange);
});

onUnmounted(() => {
    document.removeEventListener('visibilitychange', handleVisibilityChange);
});


// --- HELPERS (MODIFICADO) ---
const selectTab = (tab) => {
    activeTab.value = tab;
    reload(true); // Al cambiar de pestaña, es razonable resetear la página a la 1
};
</script>

<template>
    <Head title="Revisión Diaria (Checklist)" />

    <AuthenticatedLayout>
        <template #header>
             <!-- --- INICIO: MODIFICACIÓN HEADER --- -->
             <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Revisión Diaria (Checklist)
                </h2>
                <!-- Botón de Exportar -->
                 <a :href="exportUrl"
                    class="inline-flex items-center px-4 py-2 bg-blue-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-600 active:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150 shadow-sm"
                    download>
                     <ArrowDownTrayIcon class="w-4 h-4 mr-2"/>
                     Exportar Pendientes
                 </a>
             </div>
             <!-- --- FIN: MODIFICACIÓN HEADER --- -->
        </template>

        <div class="py-10">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

                <!-- Pestañas (Tabs) -->
                <div class="mb-6 px-4 sm:px-0">
                    <nav class="flex flex-wrap gap-2 sm:gap-4" aria-label="Tabs">
                        <!-- Botón Casos -->
                        <button @click="selectTab('casos')"
                                :class="[
                                    activeTab === 'casos'
                                        ? 'bg-blue-500 text-white shadow-md'
                                        : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/60',
                                    'flex items-center py-2.5 px-4 rounded-lg font-medium text-sm transition-all duration-200 ease-in-out'
                                ]">
                            <FolderIcon class="w-5 h-5 mr-2" />
                            <span>Casos</span>
                            <span :class="[
                                    activeTab === 'casos' ? 'bg-blue-400 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200',
                                    'ml-2 text-xs font-semibold py-0.5 px-2 rounded-full'
                                ]">
                                {{ pendientesCounts ? pendientesCounts.casos : 0 }}
                            </span>
                        </button>
                        <!-- Botón Radicados -->
                        <button @click="selectTab('radicados')"
                                :class="[
                                    activeTab === 'radicados'
                                        ? 'bg-blue-500 text-white shadow-md'
                                        : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/60',
                                    'flex items-center py-2.5 px-4 rounded-lg font-medium text-sm transition-all duration-200 ease-in-out'
                                ]">
                            <DocumentTextIcon class="w-5 h-5 mr-2" />
                            <span>Radicados</span>
                            <span :class="[
                                    activeTab === 'radicados' ? 'bg-blue-400 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200',
                                    'ml-2 text-xs font-semibold py-0.5 px-2 rounded-full'
                                ]">
                                {{ pendientesCounts ? pendientesCounts.radicados : 0 }}
                            </span>
                        </button>
                        <!-- Botón Contratos -->
                        <button @click="selectTab('contratos')"
                                :class="[
                                    activeTab === 'contratos'
                                        ? 'bg-blue-500 text-white shadow-md'
                                        : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/60',
                                    'flex items-center py-2.5 px-4 rounded-lg font-medium text-sm transition-all duration-200 ease-in-out'
                                ]">
                            <ClipboardDocumentCheckIcon class="w-5 h-5 mr-2" />
                            <span>Contratos</span>
                            <span :class="[
                                    activeTab === 'contratos' ? 'bg-blue-400 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200',
                                    'ml-2 text-xs font-semibold py-0.5 px-2 rounded-full'
                                ]">
                                {{ pendientesCounts ? pendientesCounts.contratos : 0 }}
                            </span>
                        </button>
                    </nav>
                </div>
    if (!persona) return 'N/A';
    if (typeof persona === 'string') return persona;
    if (persona.nombre_completo) return persona.nombre_completo;
    if (persona.name) return persona.name;
    if (persona.nombre) return persona.nombre;
    if (persona.razon_social) return persona.razon_social;
    const nombreCompleto = `${persona.nombres || ''} ${persona.apellidos || ''}`.trim();
    return nombreCompleto || 'N/A';
};

const formatFecha = (fechaISO) => {
    if (!fechaISO) return '';
    const date = new Date(fechaISO);
    date.setMinutes(date.getMinutes() + date.getTimezoneOffset());
    return date.toLocaleDateString('es-CO', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};
</script>

<template>
    <Head title="Revisión Diaria (Checklist)" />

    <AuthenticatedLayout>
        <template #header>
             <!-- --- INICIO: MODIFICACIÓN HEADER --- -->
             <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Revisión Diaria (Checklist)
                </h2>
                <!-- Botón de Exportar -->
                 <a :href="exportUrl"
                    class="inline-flex items-center px-4 py-2 bg-blue-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-600 active:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150 shadow-sm"
                    download>
                     <ArrowDownTrayIcon class="w-4 h-4 mr-2"/>
                     Exportar Pendientes
                 </a>
             </div>
             <!-- --- FIN: MODIFICACIÓN HEADER --- -->
        </template>

        <div class="py-10">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

                <!-- Pestañas (Tabs) -->
                <div class="mb-6 px-4 sm:px-0">
                    <nav class="flex flex-wrap gap-2 sm:gap-4" aria-label="Tabs">
                        <!-- Botón Casos -->
                        <button @click="activeTab = 'casos'"
                                :class="[
                                    activeTab === 'casos'
                                        ? 'bg-blue-500 text-white shadow-md'
                                        : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/60',
                                    'flex items-center py-2.5 px-4 rounded-lg font-medium text-sm transition-all duration-200 ease-in-out'
                                ]">
                            <FolderIcon class="w-5 h-5 mr-2" />
                            <span>Casos</span>
                            <span :class="[
                                    activeTab === 'casos' ? 'bg-blue-400 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200',
                                    'ml-2 text-xs font-semibold py-0.5 px-2 rounded-full'
                                ]">
                                {{ pendientesCounts ? pendientesCounts.casos : 0 }}
                            </span>
                        </button>
                        <!-- Botón Radicados -->
                        <button @click="activeTab = 'radicados'"
                                :class="[
                                    activeTab === 'radicados'
                                        ? 'bg-blue-500 text-white shadow-md'
                                        : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/60',
                                    'flex items-center py-2.5 px-4 rounded-lg font-medium text-sm transition-all duration-200 ease-in-out'
                                ]">
                            <DocumentTextIcon class="w-5 h-5 mr-2" />
                            <span>Radicados</span>
                            <span :class="[
                                    activeTab === 'radicados' ? 'bg-blue-400 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200',
                                    'ml-2 text-xs font-semibold py-0.5 px-2 rounded-full'
                                ]">
                                {{ pendientesCounts ? pendientesCounts.radicados : 0 }}
                            </span>
                        </button>
                        <!-- Botón Contratos -->
                        <button @click="activeTab = 'contratos'"
                                :class="[
                                    activeTab === 'contratos'
                                        ? 'bg-blue-500 text-white shadow-md'
                                        : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/60',
                                    'flex items-center py-2.5 px-4 rounded-lg font-medium text-sm transition-all duration-200 ease-in-out'
                                ]">
                            <ClipboardDocumentCheckIcon class="w-5 h-5 mr-2" />
                            <span>Contratos</span>
                            <span :class="[
                                    activeTab === 'contratos' ? 'bg-blue-400 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200',
                                    'ml-2 text-xs font-semibold py-0.5 px-2 rounded-full'
                                ]">
                                {{ pendientesCounts ? pendientesCounts.contratos : 0 }}
                            </span>
                        </button>
                    </nav>
                </div>

                <!-- Campo de Búsqueda Dinámico -->
                <div class="mb-5 px-4 sm:px-0">
                    <div class="relative rounded-lg shadow-sm">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <MagnifyingGlassIcon class="w-5 h-5 text-gray-400" />
                        </div>

                        <input v-if="activeTab === 'casos'"
                               v-model="searchCasos"
                               type="search"
                               placeholder="Buscar en casos (por deudor, cooperativa, ref. crédito...)"
                               class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg leading-5 bg-white dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" />

                        <input v-if="activeTab === 'radicados'"
                               v-model="searchRadicados"
                               type="search"
                               placeholder="Buscar en radicados (por nº radicado, demandante, demandado...)"
                               class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg leading-5 bg-white dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" />

                        <input v-if="activeTab === 'contratos'"
                               v-model="searchContratos"
                               type="search"
                               placeholder="Buscar en contratos (por nº contrato, cliente...)"
                               class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg leading-5 bg-white dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" />
                    </div>
                </div>

                <!-- Filtros de Fecha y Abogado (MODIFICADO) -->
                <div class="mb-5 px-4 sm:px-0">
                    <div class="flex flex-col sm:flex-row gap-4">
                        <!-- Fecha de Inicio -->
                        <div class="flex-1">
                            <label for="start_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <span v-if="activeTab === 'casos'">Fecha Vencimiento (Desde)</span>
                                <span v-if="activeTab === 'radicados'">Fecha Revisión (Desde)</span>
                                <span v-if="activeTab === 'contratos'">Fecha Creación (Desde)</span>
                            </label>
                            <DatePicker v-model="startDate"
                                   id="start_date"
                                   class="block w-full" />
                        </div>
                        <!-- Fecha de Fin -->
                        <div class="flex-1">
                            <label for="end_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <span v-if="activeTab === 'casos'">Fecha Vencimiento (Hasta)</span>
                                <span v-if="activeTab === 'radicados'">Fecha Revisión (Hasta)</span>
                                <span v-if="activeTab === 'contratos'">Fecha Creación (Hasta)</span>
                            </label>
                            <DatePicker v-model="endDate"
                                   id="end_date"
                                   class="block w-full" />
                        </div>

                        <!-- --- INICIO: FILTRO ABOGADO --- -->
                        <div class="flex-1">
                            <label for="abogado_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Abogado Responsable
                            </label>
                            <Dropdown align="left" width="full">
                                <template #trigger>
                                    <button type="button" class="w-full flex items-center justify-between gap-2 border border-gray-300 rounded-lg shadow-sm leading-5 bg-white dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 transition-all cursor-pointer">
                                        <span class="truncate">{{ abogadoId ? abogados.find(a => a.id === abogadoId)?.name : 'Todos los Abogados' }}</span>
                                        <ChevronDownIcon class="h-4 w-4 text-gray-400" />
                                    </button>
                                </template>
                                <template #content>
                                    <div class="py-1 bg-white dark:bg-gray-800 max-h-60 overflow-y-auto">
                                        <button @click="abogadoId = ''" class="block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700" :class="{ 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-400 font-bold': abogadoId === '' }">
                                            Todos los Abogados
                                        </button>
                                        <button v-for="abogado in abogados" :key="abogado.id" @click="abogadoId = abogado.id" class="block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700" :class="{ 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-400 font-bold': abogadoId === abogado.id }">
                                            {{ abogado.name }}
                                        </button>
                                    </div>
                                </template>
                            </Dropdown>
                        </div>
                        <!-- --- FIN: FILTRO ABOGADO --- -->

                    </div>
                </div>

                <!-- Barra de Progreso Dinámica -->
                <div class="mb-5 px-4 sm:px-0" v-if="progresoActual.totalItems > 0">
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-sm font-semibold text-indigo-700 dark:text-indigo-300">Progreso de Revisión</span>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ progresoActual.texto }}</span>
                    </div>
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5 shadow-inner">
                        <div class="bg-blue-500 h-2.5 rounded-full transition-all duration-500 ease-out"
                             :style="{ width: progresoActual.porcentaje + '%' }">
                        </div>
                    </div>
                </div>

                <!-- Contenido de Pestañas -->
                <div class="mt-4">

                    <!-- Pestaña Casos (MODIFICADO V-IF) -->
                    <div v-show="activeTab === 'casos'">
                        <!-- Estado 1: Sin resultados de búsqueda -->
                        <div v-if="casos.data.length === 0 && (filters.search_casos || filters.start_date || filters.end_date || filters.abogado_id)" class="text-center p-12 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                                <MagnifyingGlassIcon class="mx-auto h-16 w-16 text-gray-400" />
                            <h3 class="mt-4 text-xl font-semibold text-gray-900 dark:text-gray-100">Sin resultados</h3>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No se encontraron casos que coincidan con tus filtros.</p>
                        </div>
                        <!-- Estado 2: Todo al día (sin búsqueda) -->
                        <div v-else-if="casos.data.length === 0" class="text-center p-12 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                            <CheckCircleIcon class="mx-auto h-16 w-16 text-green-500" />
                            <h3 class="mt-4 text-xl font-semibold text-gray-900 dark:text-gray-100">¡Todo al día!</h3>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No hay casos activos para revisar hoy.</p>
                        </div>

                        <!-- Estado 3: Lista de ítems -->
                        <TransitionGroup v-else tag="ul" name="list-item" class="space-y-3">
                            <li v-for="caso in sortedCasos" :key="`caso-${caso.id}`" class="list-item">
                                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4 flex items-center space-x-4 transition-all duration-300 ease-in-out hover:shadow-md"
                                     :class="{ 'opacity-60 bg-gray-50 dark:bg-gray-800/50': caso.revisadoHoy }">

                                    <button @click="toggleRevision(caso, 'Caso')"
                                            class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center transition-all duration-200 ease-in-out group relative"
                                            :class="[
                                                caso.revisadoHoy
                                                    ? 'bg-blue-500 text-white hover:bg-blue-600'
                                                    : 'bg-gray-100 dark:bg-gray-700 text-gray-400 dark:text-gray-500 hover:bg-blue-100 dark:hover:bg-blue-900/50 hover:text-blue-600 dark:hover:text-blue-400'
                                            ]"
                                            :title="caso.revisadoHoy ? 'Marcar como pendiente' : 'Marcar como revisado'">
                                        <CheckIcon v-if="caso.revisadoHoy" class="w-6 h-6" />
                                        <PencilSquareIcon v-else class="w-5 h-5"/>
                                         <span v-if="!caso.revisadoHoy" class="absolute -top-7 left-1/2 transform -translate-x-1/2 px-2 py-1 bg-gray-700 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap dark:bg-gray-900">
                                            Marcar
                                        </span>
                                    </button>

                                    <div class="flex-1 min-w-0">
                                        <Link :href="route('casos.show', caso.id)"
                                              class="text-base font-semibold text-indigo-600 dark:text-indigo-400 hover:underline truncate"
                                              :class="{ 'line-through text-gray-500 dark:text-gray-400 no-underline hover:no-underline': caso.revisadoHoy }">
                                            {{ formatNombre(caso.deudor) }}
                                        </Link>
                                        <p class="text-sm text-gray-600 dark:text-gray-300 truncate"
                                           :class="{ 'line-through': caso.revisadoHoy }">
                                            {{ caso.cooperativa ? caso.cooperativa.nombre : 'N/A' }}
                                        </p>
                                        <p v-if="!caso.revisadoHoy && caso.ultimaRevisionFecha"
                                           class="text-xs text-gray-400 dark:text-gray-500 mt-1"
                                           :title="`Última revisión: ${formatFecha(caso.ultimaRevisionFecha)}`">
                                             Últ. revisión: {{ formatFecha(caso.ultimaRevisionFecha) }}
                                        </p>
                                    </div>

                                    <div class="text-right flex-shrink-0 hidden sm:block">
                                        <p class="text-sm font-medium text-gray-800 dark:text-gray-100"
                                           :class="{ 'line-through': caso.revisadoHoy }">
                                            {{ caso.referencia_credito }}
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Ref. Crédito</p>
                                    </div>
                                </div>
                            </li>
                        </TransitionGroup>
                        <Pagination :links="casos.links" class="mt-6 flex justify-center" />
                    </div>

                    <!-- Pestaña Radicados (MODIFICADO V-IF) -->
                    <div v-show="activeTab === 'radicados'">
                        <div v-if="radicados.data.length === 0 && (filters.search_radicados || filters.start_date || filters.end_date || filters.abogado_id)" class="text-center p-12 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                            <MagnifyingGlassIcon class="mx-auto h-16 w-16 text-gray-400" />
                            <h3 class="mt-4 text-xl font-semibold text-gray-900 dark:text-gray-100">Sin resultados</h3>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No se encontraron radicados que coincidan con tus filtros.</p>
                        </div>
                        <div v-else-if="radicados.data.length === 0" class="text-center p-12 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                           <CheckCircleIcon class="mx-auto h-16 w-16 text-green-500" />
                            <h3 class="mt-4 text-xl font-semibold text-gray-900 dark:text-gray-100">¡Todo al día!</h3>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No hay radicados activos para revisar hoy.</p>
                        </div>

                        <TransitionGroup v-else tag="ul" name="list-item" class="space-y-3">
                            <li v-for="radicado in sortedRadicados" :key="`radicado-${radicado.id}`" class="list-item">
                                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4 flex items-center space-x-4 transition-all duration-300 ease-in-out hover:shadow-md"
                                     :class="{ 'opacity-60 bg-gray-50 dark:bg-gray-800/50': radicado.revisadoHoy }">

                                    <button @click="toggleRevision(radicado, 'ProcesoRadicado')"
                                            class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center transition-all duration-200 ease-in-out group relative"
                                            :class="[
                                                radicado.revisadoHoy
                                                    ? 'bg-blue-500 text-white hover:bg-blue-600'
                                                    : 'bg-gray-100 dark:bg-gray-700 text-gray-400 dark:text-gray-500 hover:bg-blue-100 dark:hover:bg-blue-900/50 hover:text-blue-600 dark:hover:text-blue-400'
                                            ]"
                                            :title="radicado.revisadoHoy ? 'Marcar como pendiente' : 'Marcar como revisado'">
                                        <CheckIcon v-if="radicado.revisadoHoy" class="w-6 h-6" />
                                        <PencilSquareIcon v-else class="w-5 h-5"/>
                                         <span v-if="!radicado.revisadoHoy" class="absolute -top-7 left-1/2 transform -translate-x-1/2 px-2 py-1 bg-gray-700 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap dark:bg-gray-900">
                                            Marcar
                                        </span>
                                    </button>

                                    <div class="flex-1 min-w-0">
                                        <Link :href="route('procesos.show', radicado.id)"
                                              class="text-base font-semibold text-indigo-600 dark:text-indigo-400 hover:underline truncate"
                                              :class="{ 'line-through text-gray-500 dark:text-gray-400 no-underline hover:no-underline': radicado.revisadoHoy }">
                                            {{ radicado.radicado }}
                                        </Link>
                                        <p class="text-sm text-gray-600 dark:text-gray-300 truncate"
                                           :class="{ 'line-through': radicado.revisadoHoy }">
                                            <span class="font-medium">Dte:</span> {{ formatNombre(radicado.demandante) }}
                                        </p>
                                        <p class="text-sm text-gray-600 dark:text-gray-300 truncate"
                                           :class="{ 'line-through': radicado.revisadoHoy }">
                                            <span class="font-medium">Ddo:</span> {{ formatNombre(radicado.demandado) }}
                                        </p>
                                        <p v-if="!radicado.revisadoHoy && radicado.ultimaRevisionFecha"
                                           class="text-xs text-gray-400 dark:text-gray-500 mt-1"
                                           :title="`Última revisión: ${formatFecha(radicado.ultimaRevisionFecha)}`">
                                             Últ. revisión: {{ formatFecha(radicado.ultimaRevisionFecha) }}
                                        </p>
                                    </div>
                                </div>
                            </li>
                        </TransitionGroup>
                        <Pagination :links="radicados.links" class="mt-6 flex justify-center" />
                    </div>

                    <!-- Pestaña Contratos (MODIFICADO V-IF) -->
                    <div v-show="activeTab === 'contratos'">
                        <div v-if="contratos.data.length === 0 && (filters.search_contratos || filters.start_date || filters.end_date || filters.abogado_id)" class="text-center p-12 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                            <MagnifyingGlassIcon class="mx-auto h-16 w-16 text-gray-400" />
                            <h3 class="mt-4 text-xl font-semibold text-gray-900 dark:text-gray-100">Sin resultados</h3>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No se encontraron contratos que coincidan con tus filtros.</p>
                        </div>
                        <div v-else-if="contratos.data.length === 0" class="text-center p-12 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                            <CheckCircleIcon class="mx-auto h-16 w-16 text-green-500" />
                            <h3 class="mt-4 text-xl font-semibold text-gray-900 dark:text-gray-100">¡Todo al día!</h3>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No hay contratos activos para revisar hoy.</p>
                        </div>

                        <TransitionGroup v-else tag="ul" name="list-item" class="space-y-3">
                            <li v-for="contrato in sortedContratos" :key="`contrato-${contrato.id}`" class="list-item">
                                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4 flex items-center space-x-4 transition-all duration-300 ease-in-out hover:shadow-md"
                                     :class="{ 'opacity-60 bg-gray-50 dark:bg-gray-800/50': contrato.revisadoHoy }">

                                    <button @click="toggleRevision(contrato, 'Contrato')"
                                            class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center transition-all duration-200 ease-in-out group relative"
                                            :class="[
                                                contrato.revisadoHoy
                                                    ? 'bg-blue-500 text-white hover:bg-blue-600'
                                                    : 'bg-gray-100 dark:bg-gray-700 text-gray-400 dark:text-gray-500 hover:bg-blue-100 dark:hover:bg-blue-900/50 hover:text-blue-600 dark:hover:text-blue-400'
                                            ]"
                                            :title="contrato.revisadoHoy ? 'Marcar como pendiente' : 'Marcar como revisado'">
                                        <CheckIcon v-if="contrato.revisadoHoy" class="w-6 h-6" />
                                         <PencilSquareIcon v-else class="w-5 h-5"/>
                                         <span v-if="!contrato.revisadoHoy" class="absolute -top-7 left-1/2 transform -translate-x-1/2 px-2 py-1 bg-gray-700 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap dark:bg-gray-900">
                                            Marcar
                                        </span>
                                    </button>

                                    <div class="flex-1 min-w-0">
                                        <Link :href="route('honorarios.contratos.show', contrato.id)"
                                              class="text-base font-semibold text-indigo-600 dark:text-indigo-400 hover:underline truncate"
                                              :class="{ 'line-through text-gray-500 dark:text-gray-400 no-underline hover:no-underline': contrato.revisadoHoy }">
                                            Contrato #{{ contrato.id }}
                                        </Link>
                                        <p class="text-sm text-gray-600 dark:text-gray-300 truncate"
                                           :class="{ 'line-through': contrato.revisadoHoy }">
                                            {{ formatNombre(contrato.cliente) }}
                                        </p>
                                        <p v-if="!contrato.revisadoHoy && contrato.ultimaRevisionFecha"
                                           class="text-xs text-gray-400 dark:text-gray-500 mt-1"
                                           :title="`Última revisión: ${formatFecha(contrato.ultimaRevisionFecha)}`">
                                             Últ. revisión: {{ formatFecha(contrato.ultimaRevisionFecha) }}
                                        </p>
                                    </div>

                                    <div class="text-right flex-shrink-0">
                                        <span class="px-2.5 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full"
                                          :class="{
                                                'bg-green-100 text-green-800 dark:bg-green-900/60 dark:text-green-200': contrato.estado === 'ACTIVO',
                                                'bg-red-100 text-red-800 dark:bg-red-900/60 dark:text-red-200': contrato.estado === 'CERRADO',
                                                'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/60 dark:text-yellow-200': contrato.estado === 'ARCHIVADO',
                                                'bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-200': contrato.estado === 'SALDADO',
                                                'bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-200': !['ACTIVO', 'CERRADO', 'ARCHIVADO', 'SALDADO'].includes(contrato.estado)
                                          }">
                                            {{ contrato.estado }}
                                        </span>
                                    </div>
                                </div>
                            </li>
                        </TransitionGroup>
                        <Pagination :links="contratos.links" class="mt-6 flex justify-center" />
                    </div>

                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<!-- Estilos para la animación de la lista -->
<style>
.list-item-move,
.list-item-enter-active,
.list-item-leave-active {
    transition: all 0.5s cubic-bezier(0.55, 0, 0.1, 1);
}
.list-item-enter-from,
.list-item-leave-to {
    opacity: 0;
    transform: scale(0.95);
}
.list-item-leave-active {
    position: absolute;
}
</style>

