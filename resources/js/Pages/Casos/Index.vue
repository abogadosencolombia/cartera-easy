<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Dropdown from '@/Components/Dropdown.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, watch, reactive, computed } from 'vue';
import { TrashIcon, MagnifyingGlassIcon, InboxIcon, EyeIcon, ArrowDownTrayIcon, FunnelIcon, ArchiveBoxXMarkIcon, ChevronDownIcon, XMarkIcon, DocumentDuplicateIcon, CloudArrowUpIcon } from '@heroicons/vue/24/outline'; 
import { debounce } from 'lodash';

const props = defineProps({
    casos: Object,
    can: Object,
    filters: Object,
    abogados: Array,
    cooperativas: Array,
    etapas_procesales: Array,
});

// --- Lógica de Búsqueda y Filtros Combinada ---
const filterForm = reactive({
    search: props.filters?.search ?? '',
    abogado_id: props.filters?.abogado_id ?? '',
    cooperativa_id: props.filters?.cooperativa_id ?? '',
    etapa_procesal: props.filters?.etapa_procesal ?? '',
    sin_radicado: props.filters?.sin_radicado === 'true' || props.filters?.sin_radicado === true,
});

const isDirty = computed(() => {
    return filterForm.search !== '' || 
           filterForm.abogado_id !== '' || 
           filterForm.cooperativa_id !== '' || 
           filterForm.etapa_procesal !== '' ||
           filterForm.sin_radicado === true;
});

const resetFilters = () => {
    filterForm.search = '';
    filterForm.abogado_id = '';
    filterForm.cooperativa_id = '';
    filterForm.etapa_procesal = '';
    filterForm.sin_radicado = false;
};

watch(filterForm, debounce(() => {
    router.get(route('casos.index'), 
        { 
            search: filterForm.search, 
            abogado_id: filterForm.abogado_id,
            cooperativa_id: filterForm.cooperativa_id,
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

                <!-- Barra de Filtros Avanzada -->
                <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
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
                            <select 
                                v-model="filterForm.cooperativa_id"
                                class="block w-full py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-sm focus:ring-indigo-500 focus:border-indigo-500 dark:text-white"
                            >
                                <option value="">Todas las cooperativas</option>
                                <option v-for="c in cooperativas" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                            </select>
                        </div>

                        <!-- Abogado -->
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1 ml-1">Responsable</label>
                            <select 
                                v-model="filterForm.abogado_id"
                                class="block w-full py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-sm focus:ring-indigo-500 focus:border-indigo-500 dark:text-white"
                            >
                                <option value="">Todos los abogados</option>
                                <option v-for="a in abogados" :key="a.id" :value="a.id">{{ a.name }}</option>
                            </select>
                        </div>

                        <!-- Etapa -->
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1 ml-1">Etapa Procesal</label>
                            <select 
                                v-model="filterForm.etapa_procesal"
                                class="block w-full py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-sm focus:ring-indigo-500 focus:border-indigo-500 dark:text-white"
                            >
                                <option value="">Todas las etapas</option>
                                <option v-for="e in etapas_procesales" :key="e" :value="e">{{ e }}</option>
                            </select>
                        </div>

                        <!-- Filtro Sin Radicado -->
                        <div class="flex items-center gap-3 bg-gray-50 dark:bg-gray-700 p-2.5 rounded-lg border border-gray-100 dark:border-gray-600 h-[38px] mt-5 self-start">
                            <label class="flex items-center gap-2 cursor-pointer w-full">
                                <input 
                                    v-model="filterForm.sin_radicado"
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
                                
                                <tr v-else v-for="caso in casos.data" :key="caso.id" class="hover:bg-indigo-50/30 dark:hover:bg-indigo-900/10 transition-colors group">
                                    <!-- Deudor / Expediente -->
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col">
                                            <Link :href="route('casos.show', caso.id)" class="text-sm font-black text-indigo-600 dark:text-indigo-400 hover:underline">
                                                {{ caso.deudor?.nombre_completo ?? 'Sin deudor' }}
                                            </Link>
                                            <div class="flex items-center gap-1.5 mt-1">
                                                <span class="text-[10px] font-bold px-1.5 py-0.5 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 rounded">
                                                    ID #{{ caso.id }}
                                                </span>
                                                <span v-if="caso.radicado" class="text-[10px] text-gray-400 flex items-center gap-1 group/rad">
                                                    Rad: {{ caso.radicado }}
                                                    <button @click="copyToClipboard(caso.radicado)" class="opacity-0 group-hover/rad:opacity-100 transition-opacity">
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
                                        <div class="flex flex-wrap gap-1">
                                            <template v-if="caso.users && caso.users.length > 0">
                                                <span v-for="u in caso.users" :key="u.id" class="text-[10px] font-bold text-gray-500 dark:text-gray-400 flex items-center bg-gray-50 dark:bg-gray-700 px-1.5 py-0.5 rounded border border-gray-100 dark:border-gray-600">
                                                    {{ u.name.split(' ')[0] }}
                                                </span>
                                            </template>
                                            <span v-else class="text-[10px] text-gray-400">Sin asignar</span>
                                        </div>
                                    </td>

                                    <!-- Actualización -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex flex-col">
                                            <span class="text-xs font-bold text-gray-700 dark:text-gray-300">{{ formatDate(caso.updated_at) }}</span>
                                            <span class="text-[10px] text-gray-400 italic">hace {{ formatTimeAgo(caso.updated_at) }}</span>
                                        </div>
                                    </td>

                                    <!-- Acciones Sticky -->
                                    <td class="sticky right-0 bg-white dark:bg-gray-800 group-hover:bg-indigo-50/50 dark:group-hover:bg-gray-700 px-6 py-4 whitespace-nowrap text-right z-10 shadow-[-4px_0_6px_-2px_rgba(0,0,0,0.05)] transition-colors">
                                        <div class="flex items-center justify-end gap-2">
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

            </div>
        </div>

        <Modal :show="confirmingCaseDeletion" @close="closeModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    ¿Estás seguro de que quieres eliminar este caso?
                </h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400" v-if="caseToDelete">
                    Estás a punto de eliminar permanentemente el Caso #{{ caseToDelete.id }} del deudor
                    <span class="font-bold">{{ caseToDelete.deudor?.nombre_completo ?? 'N/A' }}</span>. Esta acción no se puede deshacer.
                </p>
                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="closeModal">Cancelar</SecondaryButton>
                    <DangerButton class="ms-3" :class="{ 'opacity-25': deleteForm.processing }" :disabled="deleteForm.processing" @click="deleteCase">
                        Sí, Eliminar Caso
                    </DangerButton>
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
