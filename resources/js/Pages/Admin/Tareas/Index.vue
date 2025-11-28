<script setup>
import { ref, watch } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import Textarea from '@/Components/Textarea.vue';
import Pagination from '@/Components/Pagination.vue';
import { debounce } from 'lodash';
import axios from 'axios';
import {
    DocumentTextIcon,
    FolderIcon,
    BuildingLibraryIcon,
    ArrowPathIcon,
    ExclamationTriangleIcon,
    LinkIcon, // <-- Nuevo icono
    XCircleIcon // <-- Nuevo icono
} from '@heroicons/vue/24/outline';

const props = defineProps({
    tareas: { type: Object, required: true },
    usuarios: { type: Array, required: true },
    filtros: { type: Object, default: () => ({}) },
});

// --- Lógica de Creación de Tarea ---
const form = useForm({
    titulo: '',
    descripcion: '',
    user_id: null,
    tarea_type: null, // <-- Se setea al seleccionar
    tarea_id: null,   // <-- Se setea al seleccionar
});
// Guardamos el texto del elemento seleccionado para mostrarlo en la UI
const selectedItemText = ref(''); 

// ==========================================================
// --- INICIO: LÓGICA DEL NUEVO MODAL DE BÚSQUEDA ---
// ==========================================================

const showSearchModal = ref(false); // Controla la visibilidad del modal
const currentSearchTab = ref('proceso'); // Pestaña actual (proceso, caso, contrato)

const searchQuery = ref('');    // Query de búsqueda DENTRO del modal
const searchResults = ref([]);  // Resultados DENTRO del modal
const isSearching = ref(false); // Estado de carga DENTRO del modal

const axiosHeaders = {
    headers: {
        'Cache-Control': 'no-cache',
        'Pragma': 'no-cache',
        'Expires': '0',
    }
};

// Función UNICA de búsqueda para el modal
const buscarEnModal = debounce(async () => {
    isSearching.value = true;
    searchResults.value = [];
    
    let routeName = '';
    switch (currentSearchTab.value) {
        case 'proceso': routeName = 'admin.tareas.buscar.procesos'; break;
        case 'caso': routeName = 'admin.tareas.buscar.casos'; break;
        case 'contrato': routeName = 'admin.tareas.buscar.contratos'; break;
    }

    if (!routeName) {
        isSearching.value = false;
        return;
    }

    try {
        const response = await axios.get(route(routeName), {
            params: { q: searchQuery.value },
            ...axiosHeaders
        });
        searchResults.value = response.data.length > 0 ? response.data : [{ id: 'no-results', texto: 'No se encontraron resultados.' }];
    } catch (error) {
        console.error(`Error buscando ${currentSearchTab.value}:`, error);
        searchResults.value = [{ id: 'error', texto: 'Error al cargar resultados.' }];
    } finally {
        isSearching.value = false;
    }
}, 300);

// Watcher para el input de búsqueda del modal
watch(searchQuery, buscarEnModal);

// Función para cambiar de pestaña
const changeSearchTab = (tabName) => {
    currentSearchTab.value = tabName;
    searchQuery.value = ''; // Limpiar búsqueda
    searchResults.value = []; // Limpiar resultados
    buscarEnModal(); // Cargar sugerencias
};

// Función para abrir el modal
const openSearchModal = () => {
    changeSearchTab('proceso'); // Abrir siempre en la primera pestaña
    showSearchModal.value = true;
};

// Función para seleccionar un item del modal
const selectItem = (item) => {
    if (item.id === 'error' || item.id === 'no-results') return;

    form.tarea_type = currentSearchTab.value; // 'proceso', 'caso', o 'contrato'
    form.tarea_id = item.id;
    selectedItemText.value = item.texto; // Guardar el texto para la UI
    
    showSearchModal.value = false; // Cerrar modal
    // Resetear estado del modal
    searchQuery.value = '';
    searchResults.value = [];
};

// Función para des-vincular un elemento
const removeSelectedItem = () => {
    form.tarea_type = null;
    form.tarea_id = null;
    selectedItemText.value = '';
};

// ==========================================================
// --- FIN: LÓGICA DEL NUEVO MODAL DE BÚSQUEDA ---
// ==========================================================


// --- Función para enviar formulario ---
const submitCreateTask = () => {
    form.post(route('admin.tareas.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            removeSelectedItem(); // Limpiar la vinculación
        },
    });
};

// --- Lógica de Filtros de la Tabla (Sin cambios) ---
const filtroEstado = ref(props.filtros.estado || 'todos');
const filtroUsuario = ref(props.filtros.user_id ? Number(props.filtros.user_id) : 'todos');

const applyFilters = debounce(() => {
    router.get(route('admin.tareas.index'), {
        estado: filtroEstado.value,
        user_id: filtroUsuario.value,
    }, {
        preserveState: true,
        replace: true,
    });
}, 300);

watch(filtroEstado, applyFilters);
watch(filtroUsuario, applyFilters);

// --- Lógica de Eliminación (Sin cambios) ---
const showDeleteModal = ref(false);
const tareaToDelete = ref(null);

const openDeleteModal = (tarea) => {
    tareaToDelete.value = tarea;
    showDeleteModal.value = true;
};

const submitDeleteTask = () => {
    if (!tareaToDelete.value) return;
    router.delete(route('admin.tareas.destroy', tareaToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
            tareaToDelete.value = null;
        }
    });
};

// --- Helpers (Sin cambios) ---
const getUserInitials = (name) => {
    if (!name) return '??';
    const parts = name.split(' ');
    if (parts.length > 1) {
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }
    return name.substring(0, 2).toUpperCase();
};

const formatRelativeTime = (dateString) => {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    const now = new Date();
    const diffInSeconds = Math.floor((now - date) / 1000);
    
    const rtf = new Intl.RelativeTimeFormat('es', { numeric: 'auto' });

    if (diffInSeconds < 60) { return rtf.format(-diffInSeconds, 'second'); }
    const diffInMinutes = Math.floor(diffInSeconds / 60);
    if (diffInMinutes < 60) { return rtf.format(-diffInMinutes, 'minute'); }
    const diffInHours = Math.floor(diffInMinutes / 60);
    if (diffInHours < 24) { return rtf.format(-diffInHours, 'hour'); }
    const diffInDays = Math.floor(diffInHours / 24);
    if (diffInDays < 7) { return rtf.format(-diffInDays, 'day'); }
    return date.toLocaleDateString('es-CO', { day: '2-digit', month: 'short', year: 'numeric' });
};

const getElementLink = (tarea) => {
    if (!tarea.tarea) return '#';
    if (tarea.tarea_type.includes('ProcesoRadicado')) { return route('procesos.show', tarea.tarea_id); }
    if (tarea.tarea_type.includes('Caso')) { return route('casos.show', tarea.tarea_id); }
    if (tarea.tarea_type.includes('Contrato')) { return route('honorarios.contratos.show', tarea.tarea_id); }
    return '#';
};

const getElementText = (tarea) => {
    if (!tarea.tarea) return `ID: ${tarea.tarea_id}`;
    if (tarea.tarea_type.includes('ProcesoRadicado')) { return tarea.tarea.radicado || `Proceso ID: ${tarea.tarea_id}`; }
    if (tarea.tarea_type.includes('Caso')) { return tarea.tarea.numero_caso || `Caso ID: ${tarea.tarea_id}`; }
    if (tarea.tarea_type.includes('Contrato')) { return `Contrato ID: ${tarea.tarea_id}`; }
    return `ID: ${tarea.tarea_id}`;
}

</script>

<template>
    <Head title="Gestión de Tareas" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Gestión de Tareas (Solo Admin)
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Formulario de Creación de Tarea -->
                <div class="p-6 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 border-b dark:border-gray-700 pb-3">
                        Asignar Nueva Tarea
                    </h3>
                    <form @submit.prevent="submitCreateTask" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="user_id" value="Asignar a:" required />
                                <select
                                    id="user_id"
                                    v-model="form.user_id"
                                    class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                >
                                    <option :value="null" disabled>Seleccione un usuario...</option>
                                    <option v-for="user in usuarios" :key="user.id" :value="user.id">
                                        {{ user.name }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.user_id" class="mt-2" />
                            </div>
                            <div>
                                <InputLabel for="titulo" value="Título de la Tarea:" required />
                                <TextInput
                                    id="titulo"
                                    v-model="form.titulo"
                                    type="text"
                                    class="mt-1 block w-full"
                                    placeholder="Ej: Revisar último auto del proceso"
                                />
                                <InputError :message="form.errors.titulo" class="mt-2" />
                            </div>
                        </div>

                        <!-- =============================================== -->
                        <!-- --- INICIO: NUEVO VINCULADOR DE ELEMENTO --- -->
                        <!-- =============================================== -->
                        <div>
                            <InputLabel value="Vincular a:" required />
                            
                            <!-- Caso 1: Nada seleccionado -->
                            <div v-if="!form.tarea_id" class="mt-1">
                                <button
                                    type="button"
                                    @click="openSearchModal"
                                    class="w-full flex items-center justify-center gap-2 px-4 py-2 border border-dashed border-gray-400 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition"
                                >
                                    <LinkIcon class="h-5 w-5" />
                                    Vincular a un Proceso, Caso o Contrato
                                </button>
                            </div>

                            <!-- Caso 2: Algo seleccionado -->
                            <div v-else class="mt-1">
                                <div class="flex items-center justify-between p-3 border border-green-500 dark:border-green-700 bg-green-50 dark:bg-gray-900 rounded-md">
                                    <div class="flex items-center gap-3">
                                        <span class="flex-shrink-0 p-1.5 bg-green-100 dark:bg-green-900 rounded-full">
                                            <DocumentTextIcon v-if="form.tarea_type === 'proceso'" class="h-5 w-5 text-green-600 dark:text-green-300" />
                                            <FolderIcon v-if="form.tarea_type === 'caso'" class="h-5 w-5 text-green-600 dark:text-green-300" />
                                            <BuildingLibraryIcon v-if="form.tarea_type === 'contrato'" class="h-5 w-5 text-green-600 dark:text-green-300" />
                                        </span>
                                        <span class="text-green-800 dark:text-green-400 font-medium text-sm">{{ selectedItemText }}</span>
                                    </div>
                                    <button
                                        @click.prevent="removeSelectedItem"
                                        type="button"
                                        class="text-red-500 hover:text-red-700"
                                        title="Quitar vinculación"
                                    >
                                        <XCircleIcon class="h-5 w-5" />
                                    </button>
                                </div>
                            </div>
                            <InputError :message="form.errors.tarea_id || form.errors.tarea_type" class="mt-2" />
                        </div>
                        <!-- =============================================== -->
                        <!-- --- FIN: NUEVO VINCULADOR DE ELEMENTO --- -->
                        <!-- =============================================== -->
                        
                        <div>
                            <InputLabel for="descripcion" value="Descripción de la Tarea:" required />
                            <Textarea
                                id="descripcion"
                                v-model="form.descripcion"
                                class="mt-1 block w-full"
                                rows="3"
                                placeholder="Escribe las instrucciones detalladas para el usuario..."
                            />
                            <InputError :message="form.errors.descripcion" class="mt-2" />
                        </div>
                        
                        <div class="flex justify-end">
                            <PrimaryButton :disabled="form.processing" :class="{ 'opacity-25': form.processing }">
                                {{ form.processing ? 'Asignando...' : 'Asignar Tarea' }}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>

                <!-- Listado y Filtros de Tareas -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    
                    <!-- Filtros -->
                    <div class="p-4 bg-gray-50 dark:bg-gray-800/50 flex flex-col md:flex-row items-center gap-4">
                        <div class="flex-grow w-full md:w-auto">
                            <InputLabel for="filtro-estado" value="Filtrar por Estado" />
                            <select
                                id="filtro-estado"
                                v-model="filtroEstado"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                            >
                                <option value="todos">Todos los Estados</option>
                                <option value="pendiente">Solo Pendientes</option>
                                <option value="completada">Solo Completadas</option>
                            </select>
                        </div>
                        <div class="flex-grow w-full md:w-auto">
                            <InputLabel for="filtro-usuario" value="Filtrar por Usuario Asignado" />
                            <select
                                id="filtro-usuario"
                                v-model="filtroUsuario"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                            >
                                <option value="todos">Todos los Usuarios</option>
                                <option v-for="user in usuarios" :key="user.id" :value="user.id">
                                    {{ user.name }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Tabla de Tareas -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full w-full text-sm text-left text-gray-500 dark:text-gray-400">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                <tr>
                                    <th scope="col" class="px-6 py-3 whitespace-nowrap">Estado</th>
                                    <th scope="col" class="px-6 py-3">Tarea</th>
                                    <th scope="col" class="px-6 py-3">Asignado a</th>
                                    <th scope="col" class="px-6 py-3">Vinculado a</th>
                                    <th scope="col" class="px-6 py-3 whitespace-nowrap">Fechas</th>
                                    <th scope="col" class="px-6 py-3 whitespace-nowrap">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="tareas.data.length === 0">
                                    <td colspan="6" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                        No se encontraron tareas con los filtros seleccionados.
                                    </td>
                                </tr>
                                <tr v-for="tarea in tareas.data" :key="tarea.id" class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-150">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span
                                            class="inline-flex items-center rounded-md px-2 py-1 text-xs font-bold"
                                            :class="{
                                                'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300': tarea.estado === 'pendiente',
                                                'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300': tarea.estado === 'completada',
                                            }"
                                        >
                                            {{ tarea.estado }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 max-w-xs">
                                        <p class="font-bold text-gray-900 dark:text-white truncate" :title="tarea.titulo">
                                            {{ tarea.titulo }}
                                        </p>
                                        <p class="text-gray-600 dark:text-gray-400 truncate" :title="tarea.descripcion">
                                            {{ tarea.descripcion }}
                                        </p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <!-- Avatar de Usuario -->
                                        <div class="flex items-center gap-3">
                                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-300 font-semibold text-xs">
                                                {{ getUserInitials(tarea.asignado_a.name) }}
                                            </span>
                                            <div>
                                                <p class="font-medium text-gray-900 dark:text-white">{{ tarea.asignado_a.name }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Por: {{ tarea.creada_por.name }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <Link :href="getElementLink(tarea)" class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium">
                                            {{ getElementText(tarea) }}
                                        </Link>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <p class="font-medium" title="Fecha de asignación">
                                            Asig: {{ formatRelativeTime(tarea.created_at) }}
                                        </p>
                                        <p v-if="tarea.fecha_completado" class="text-xs text-green-600 dark:text-green-400" title="Fecha de completado">
                                            Comp: {{ formatRelativeTime(tarea.fecha_completado) }}
                                        </p>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <DangerButton @click="openDeleteModal(tarea)" small>
                                            Eliminar
                                        </DangerButton>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <Pagination v-if="tareas.links.length > 3" class="mt-6 p-4" :links="tareas.links" />

                </div>
            </div>
        </div>
        
        <!-- =============================================== -->
        <!-- --- INICIO: NUEVO MODAL DE BÚSQUEDA --- -->
        <!-- =============================================== -->
        <Modal :show="showSearchModal" @close="showSearchModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Vincular Tarea a un Elemento
                </h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Selecciona la pestaña y busca el elemento que deseas vincular.
                </p>

                <!-- Pestañas -->
                <div class="mt-4 border-b border-gray-200 dark:border-gray-700">
                    <nav class="-mb-px flex space-x-4" aria-label="Tabs">
                        <button
                            @click="changeSearchTab('proceso')"
                            :class="[
                                currentSearchTab === 'proceso'
                                    ? 'border-indigo-500 dark:border-indigo-400 text-indigo-600 dark:text-indigo-400'
                                    : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300 dark:hover:border-gray-600',
                                'group inline-flex items-center py-3 px-1 border-b-2 font-medium text-sm'
                            ]"
                        >
                            <DocumentTextIcon class="h-5 w-5 mr-2" /> Proceso/Radicado
                        </button>
                        <button
                            @click="changeSearchTab('caso')"
                            :class="[
                                currentSearchTab === 'caso'
                                    ? 'border-indigo-500 dark:border-indigo-400 text-indigo-600 dark:text-indigo-400'
                                    : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300 dark:hover:border-gray-600',
                                'group inline-flex items-center py-3 px-1 border-b-2 font-medium text-sm'
                            ]"
                        >
                            <FolderIcon class="h-5 w-5 mr-2" /> Caso
                        </button>
                        <button
                            @click="changeSearchTab('contrato')"
                            :class="[
                                currentSearchTab === 'contrato'
                                    ? 'border-indigo-500 dark:border-indigo-400 text-indigo-600 dark:text-indigo-400'
                                    : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300 dark:hover:border-gray-600',
                                'group inline-flex items-center py-3 px-1 border-b-2 font-medium text-sm'
                            ]"
                        >
                            <BuildingLibraryIcon class="h-5 w-5 mr-2" /> Contrato
                        </button>
                    </nav>
                </div>

                <!-- Contenido de Pestañas (Buscador + Resultados) -->
                <div class="mt-4">
                    <div class="relative">
                        <TextInput
                            v-model="searchQuery"
                            type="text"
                            class="w-full"
                            placeholder="Escribe para buscar..."
                        />
                        <div v-if="isSearching" class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                            <ArrowPathIcon class="h-5 w-5 text-gray-400 animate-spin" />
                        </div>
                    </div>

                    <div class="mt-4 h-64 overflow-y-auto border dark:border-gray-700 rounded-md">
                        <ul v-if="searchResults.length > 0">
                             <li v-for="item in searchResults" :key="item.id">
                                <button
                                    @click="selectItem(item)"
                                    type="button"
                                    class="w-full text-left p-3 hover:bg-indigo-50 dark:hover:bg-indigo-900/50 disabled:opacity-50 disabled:cursor-not-allowed"
                                    :disabled="item.id === 'error' || item.id === 'no-results'"
                                >
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-200"
                                       :class="{ 'text-red-600 dark:text-red-400': item.id === 'error', 'text-gray-500 dark:text-gray-400': item.id === 'no-results' }"
                                    >
                                        {{ item.texto }}
                                    </p>
                                </button>
                             </li>
                        </ul>
                        <div v-if="!isSearching && searchResults.length === 0" class="flex items-center justify-center h-full">
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Escribe en el buscador para ver resultados.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="showSearchModal = false">Cancelar</SecondaryButton>
                </div>
            </div>
        </Modal>
        <!-- =============================================== -->
        <!-- --- FIN: NUEVO MODAL DE BÚSQUEDA --- -->
        <!-- =============================================== -->


        <!-- Modal de Eliminación (Sin Cambios) -->
        <Modal :show="showDeleteModal" @close="showDeleteModal = false">
            <div class="p-6">
                <div class="flex items-center gap-3">
                    <span class="flex-shrink-0 flex items-center justify-center h-10 w-10 rounded-full bg-red-100 dark:bg-red-900/50 sm:h-8 sm:w-8">
                        <ExclamationTriangleIcon class="h-6 w-6 text-red-600 dark:text-red-400" />
                    </span>
                    <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Confirmar Eliminación</h2>
                </div>
                <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                    ¿Estás seguro de que quieres eliminar la tarea "{{ tareaToDelete?.titulo }}"? Esta acción no se puede deshacer.
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="showDeleteModal = false">Cancelar</SecondaryButton>
                    <DangerButton @click="submitDeleteTask">
                        Sí, Eliminar
                    </DangerButton>
                </div>
            </div>
        </Modal>

    </AuthenticatedLayout>
</template>

<!-- Estilos para la transición del dropdown (No se usa más, pero no hace daño) -->
<style>
.fade-scale-enter-active,
.fade-scale-leave-active {
    transition: opacity 0.1s ease, transform 0.1s ease;
}
.fade-scale-enter-from,
.fade-scale-leave-to {
    opacity: 0;
    transform: scale(0.95);
}
</style>