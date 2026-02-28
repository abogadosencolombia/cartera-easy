<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Dropdown from '@/Components/Dropdown.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, watch, reactive } from 'vue';
import { TrashIcon, MagnifyingGlassIcon, InboxIcon, EyeIcon, ArrowDownTrayIcon, FunnelIcon, ArchiveBoxXMarkIcon, ChevronDownIcon } from '@heroicons/vue/24/outline'; 
import { debounce } from 'lodash';

const props = defineProps({
    casos: Object,
    can: Object,
    filters: Object,
    abogados: Array,
});

// --- Lógica de Búsqueda y Filtros Combinada ---
const filterForm = reactive({
    search: props.filters?.search ?? '',
    abogado_id: props.filters?.abogado_id ?? '',
});

watch(filterForm, debounce(() => {
    router.get(route('casos.index'), 
        { 
            search: filterForm.search, 
            abogado_id: filterForm.abogado_id 
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
        abogado_id: filterForm.abogado_id
    });
};

// --- Lógica de Eliminación ---
const confirmingCaseDeletion = ref(false);
const caseToDelete = ref(null);
const form = useForm({});
const confirmCaseDeletion = (caso) => {
    caseToDelete.value = caso;
    confirmingCaseDeletion.value = true;
};
const deleteCase = () => {
    form.delete(route('casos.destroy', caseToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    });
};
const closeModal = () => {
    confirmingCaseDeletion.value = false;
    caseToDelete.value = null;
};

// --- Ayudantes de Estilo ---
const statusProcessoClasses = {
    'prejurídico': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
    'demandado': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
    'en ejecución': 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-300',
    'sentencia': 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
    'cerrado': 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
    'default': 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
    'DEMANDA PRESENTADA': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
};

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    if (isNaN(date.getTime())) {
        return 'Fecha inválida';
    }
    return date.toLocaleDateString('es-CO', {
        year: 'numeric',
        month: 'long',
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
</script>

<template>
    <Head title="Gestión de Casos" />

    <AuthenticatedLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <div class="flex flex-col md:flex-row justify-between items-center gap-4 px-4 sm:px-0">
                    <h2 class="font-semibold text-2xl text-gray-800 dark:text-gray-200 leading-tight">
                        Gestión de Casos
                    </h2>
                    <div class="flex flex-col sm:flex-row items-center gap-4 w-full md:w-auto">
                        
                        <!-- Filtro por Abogado -->
                        <Dropdown align="left" width="64" v-if="abogados && abogados.length > 0">
                            <template #trigger>
                                <button type="button" class="w-full md:w-64 flex items-center justify-between gap-2 bg-gray-50 dark:bg-gray-700 px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm text-gray-900 dark:text-white hover:bg-white dark:hover:bg-gray-600 transition shadow-sm">
                                    <div class="flex items-center gap-2 truncate">
                                        <FunnelIcon class="w-5 h-5 text-gray-500 dark:text-gray-400" />
                                        <span>{{ filterForm.abogado_id ? abogados.find(a => a.id === filterForm.abogado_id)?.name : 'Todos los abogados' }}</span>
                                    </div>
                                    <ChevronDownIcon class="h-4 w-4 text-gray-400" />
                                </button>
                            </template>
                            <template #content>
                                <div class="py-1 bg-white dark:bg-gray-800">
                                    <button @click="filterForm.abogado_id = ''" class="block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700" :class="{ 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-400 font-bold': filterForm.abogado_id === '' }">
                                        Todos los abogados
                                    </button>
                                    <button v-for="abogado in abogados" :key="abogado.id" @click="filterForm.abogado_id = abogado.id" class="block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700" :class="{ 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-400 font-bold': filterForm.abogado_id === abogado.id }">
                                        {{ abogado.name }}
                                    </button>
                                </div>
                            </template>
                        </Dropdown>

                        <!-- Buscador -->
                        <div class="relative w-full md:w-72">
                            <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                                <MagnifyingGlassIcon class="w-5 h-5 text-gray-500 dark:text-gray-400" />
                            </div>
                            <input
                                v-model="filterForm.search"
                                type="text"
                                name="search"
                                class="block w-full p-2.5 ps-10 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                                placeholder="Buscar caso..."
                                autocomplete="off"
                            />
                        </div>
                        
                        <div class="flex gap-2 w-full sm:w-auto">
                            <SecondaryButton 
                                @click="exportarExcel" 
                                class="justify-center flex-1 sm:flex-none" 
                                title="Descargar Excel filtrado"
                            >
                                <ArrowDownTrayIcon class="w-5 h-5 sm:mr-2" />
                                <span class="hidden sm:inline">Exportar</span>
                            </SecondaryButton>

                            <Link
                                :href="route('casos.create')"
                                class="inline-flex items-center justify-center px-4 py-2.5 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 flex-1 sm:flex-none"
                            >
                                Registrar Caso
                            </Link>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700/50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Deudor
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Nro. Caso
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Estado
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Abogado
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Fecha Creación
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-if="!casos || !casos.data || casos.data.length === 0">
                                    <td colspan="6" class="px-6 py-16 text-center">
                                        <div class="flex flex-col items-center">
                                            <InboxIcon class="h-12 w-12 text-gray-400" />
                                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mt-2">Sin resultados</h3>
                                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No se encontraron casos. Intenta con otra búsqueda o registra uno nuevo.</p>
                                            <Link :href="route('casos.create')" class="mt-4">
                                                <PrimaryButton>Registrar Primer Caso</PrimaryButton>
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                                
                                <tr v-else v-for="caso in casos.data" :key="caso.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors duration-150">
                                    <td class="px-6 py-5 whitespace-nowrap">
                                        <Link :href="route('casos.show', caso.id)" class="text-indigo-600 dark:text-indigo-400 font-bold truncate hover:underline text-sm">
                                            {{ caso.deudor?.nombre_completo ?? 'Sin deudor' }}
                                        </Link>
                                    </td>
                                    <td class="px-6 py-5 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <p class="font-medium text-gray-900 dark:text-gray-200">Caso #{{ caso.id }}</p>
                                        <p v-if="caso.radicado" class="text-xs">Rad: {{ caso.radicado }}</p>
                                        <span v-if="caso.clonado_de_id" class="text-xs italic text-yellow-600 dark:text-yellow-400">
                                            (Clonado del #{{ caso.clonado_de_id }})
                                        </span>
                                    </td>

                                    <td class="px-6 py-5 whitespace-nowrap">
                                        <div class="flex flex-col items-start gap-1">
                                            <!-- BADGE DE CERRADO (VISUALIZACIÓN) -->
                                            <span v-if="caso.nota_cierre" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-red-100 text-red-800 border border-red-200 dark:bg-red-900/30 dark:text-red-300 dark:border-red-800">
                                                <ArchiveBoxXMarkIcon class="w-3 h-3 mr-1"/> CERRADO
                                            </span>

                                            <span
                                                class="px-2.5 py-1 inline-flex text-xs leading-5 font-semibold rounded-full"
                                                :class="statusProcessoClasses[caso.etapa_procesal || caso.estado_proceso] || statusProcessoClasses['default']"
                                            >
                                                {{ formatLabel(caso.etapa_procesal || caso.estado_proceso) }}
                                            </span>
                                        </div>
                                    </td>

                                    <td class="px-6 py-5 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ caso.user ? caso.user.name : 'N/A' }}
                                    </td>
                                    <td class="px-6 py-5 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ formatDate(caso.created_at) }}
                                    </td>
                                    <td class="px-6 py-5 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex items-center justify-end gap-2">
                                            <Link
                                                :href="route('casos.show', caso.id)"
                                                class="p-2 text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition"
                                                title="Ver caso"
                                            >
                                                <span class="sr-only">Ver</span>
                                                <EyeIcon class="h-5 w-5" />
                                            </Link>
                                            
                                            <button
                                                v-if="$page.props.auth.user.tipo_usuario === 'admin'"
                                                @click="confirmCaseDeletion(caso)"
                                                class="p-2 text-gray-400 hover:text-red-600 dark:hover:text-red-400 transition"
                                                title="Eliminar caso"
                                            >
                                                <span class="sr-only">Eliminar</span>
                                                <TrashIcon class="h-5 w-5" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div v-if="casos && casos.links?.length > 1" class="p-4 flex flex-wrap justify-center gap-2 border-t dark:border-gray-700">
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
                    <DangerButton class="ms-3" :class="{ 'opacity-25': form.processing }" :disabled="form.processing" @click="deleteCase">
                        Sí, Eliminar Caso
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>