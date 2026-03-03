<script setup>
import { ref, watch } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import DatePicker from '@/Components/DatePicker.vue';
import Textarea from '@/Components/Textarea.vue';
import Pagination from '@/Components/Pagination.vue';
import { debounce } from 'lodash';
import axios from 'axios';
import {
    DocumentTextIcon, FolderIcon, BuildingLibraryIcon, ArrowPathIcon,
    ExclamationTriangleIcon, LinkIcon, XCircleIcon, ClockIcon, 
    FireIcon, CheckBadgeIcon 
    // He quitado StickyNoteIcon de aquí porque causaba el error
} from '@heroicons/vue/24/outline';

const props = defineProps({
    tareas: { type: Object, required: true },
    usuarios: { type: Array, required: true },
    filtros: { type: Object, default: () => ({}) },
});

// --- Formulario ---
const form = useForm({
    titulo: '',
    descripcion: '',
    user_id: null,
    tarea_type: null,
    tarea_id: null,
    fecha_limite: '', // Ahora es opcional
});

const selectedItemText = ref(''); 
const showSearchModal = ref(false);
const currentSearchTab = ref('proceso');
const searchQuery = ref('');
const searchResults = ref([]);
const isSearching = ref(false);

// --- Lógica de Búsqueda del Modal (Sin cambios) ---
const buscarEnModal = debounce(async () => {
    isSearching.value = true;
    searchResults.value = [];
    let routeName = '';
    switch (currentSearchTab.value) {
        case 'proceso': routeName = 'admin.tareas.buscar.procesos'; break;
        case 'caso': routeName = 'admin.tareas.buscar.casos'; break;
        case 'contrato': routeName = 'admin.tareas.buscar.contratos'; break;
    }
    if (!routeName) { isSearching.value = false; return; }
    try {
        const response = await axios.get(route(routeName), { params: { q: searchQuery.value } });
        searchResults.value = response.data.length > 0 ? response.data : [{ id: 'no-results', texto: 'No se encontraron resultados.' }];
    } catch (error) {
        searchResults.value = [{ id: 'error', texto: 'Error al cargar resultados.' }];
    } finally { isSearching.value = false; }
}, 300);

watch(searchQuery, buscarEnModal);

const changeSearchTab = (tabName) => {
    currentSearchTab.value = tabName;
    searchQuery.value = '';
    searchResults.value = [];
    buscarEnModal();
};
const openSearchModal = () => { changeSearchTab('proceso'); showSearchModal.value = true; };
const selectItem = (item) => {
    if (item.id === 'error' || item.id === 'no-results') return;
    form.tarea_type = currentSearchTab.value;
    form.tarea_id = item.id;
    selectedItemText.value = item.texto;
    showSearchModal.value = false;
    searchQuery.value = '';
    searchResults.value = [];
};
const removeSelectedItem = () => {
    form.tarea_type = null;
    form.tarea_id = null;
    selectedItemText.value = '';
};

// --- Submit ---
const submitCreateTask = () => {
    form.post(route('admin.tareas.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            removeSelectedItem();
        },
    });
};

// --- Filtros (Sin cambios) ---
const filtroEstado = ref(props.filtros.estado || 'todos');
const filtroUsuario = ref(props.filtros.user_id ? Number(props.filtros.user_id) : 'todos');
const applyFilters = debounce(() => {
    router.get(route('admin.tareas.index'), {
        estado: filtroEstado.value,
        user_id: filtroUsuario.value,
    }, { preserveState: true, replace: true });
}, 300);
watch(filtroEstado, applyFilters);
watch(filtroUsuario, applyFilters);

// --- Eliminación ---
const showDeleteModal = ref(false);
const tareaToDelete = ref(null);
const openDeleteModal = (tarea) => { tareaToDelete.value = tarea; showDeleteModal.value = true; };
const submitDeleteTask = () => {
    if (!tareaToDelete.value) return;
    router.delete(route('admin.tareas.destroy', tareaToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => { showDeleteModal.value = false; tareaToDelete.value = null; }
    });
};

// --- Helpers Visuales ---
const getUserInitials = (name) => name ? name.substring(0, 2).toUpperCase() : '??';

const getSemaforoClasses = (semaforo) => {
    switch (semaforo) {
        case 'vencida': return 'bg-red-100 text-red-800 border-red-200 animate-blink'; 
        case 'urgente': return 'bg-yellow-100 text-yellow-800 border-yellow-200'; 
        case 'tiempo': return 'bg-green-100 text-green-800 border-green-200'; 
        case 'completado': return 'bg-blue-100 text-blue-800 border-blue-200 opacity-75'; 
        case 'sin_fecha': return 'bg-gray-100 text-gray-600 border-gray-200'; // Estilo gris para notas
        default: return 'bg-gray-100 text-gray-800';
    }
};

const getSemaforoLabel = (tarea) => {
    if (tarea.estado === 'completada') return 'Completada';
    const sem = tarea.semaforo;
    if (sem === 'vencida') return '¡VENCIDA!';
    if (sem === 'urgente') return '¡Urgente!';
    if (sem === 'tiempo') return 'A tiempo';
    return 'Nota / General'; // Etiqueta cuando no hay fecha
};

const formatDateLocal = (dateString) => {
    if (!dateString) return 'Sin fecha límite';
    return new Date(dateString).toLocaleString('es-CO', { 
        month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' 
    });
};
</script>

<template>
    <Head title="Gestión de Tareas" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-blue-500 dark:text-gray-200 leading-tight">
                Gestión de Tareas y Vencimientos
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- FORMULARIO DE CREACIÓN -->
                <div class="p-6 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border-l-4 border-indigo-500">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4 flex items-center gap-2">
                        <FireIcon class="h-6 w-6 text-indigo-500"/>
                        Asignar Nueva Tarea o Nota
                    </h3>
                    <form @submit.prevent="submitCreateTask" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                            <!-- Usuario -->
                            <div class="md:col-span-4">
                                <InputLabel for="user_id" value="¿Quién lo hace?" required />
                                <select id="user_id" v-model="form.user_id"
                                    class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm">
                                    <option :value="null" disabled>Selecciona el Usuario...</option>
                                    <option v-for="user in usuarios" :key="user.id" :value="user.id">{{ user.name }}</option>
                                </select>
                                <InputError :message="form.errors.user_id" class="mt-2" />
                            </div>

                            <!-- Título -->
                            <div class="md:col-span-5">
                                <InputLabel for="titulo" value="¿Qué debe hacer?" required />
                                <TextInput id="titulo" v-model="form.titulo" type="text" class="mt-1 block w-full" placeholder="Ej: Comprar café o revisar expediente" />
                                <InputError :message="form.errors.titulo" class="mt-2" />
                            </div>

                            <!-- Fecha Límite (OPCIONAL) -->
                            <div class="md:col-span-3">
                                <div class="flex justify-between">
                                    <InputLabel for="fecha_limite" value="Fecha Límite" />
                                    <span class="text-xs text-gray-400 italic mt-1">(Opcional)</span>
                                </div>
                                <DatePicker 
                                    id="fecha_limite" 
                                    v-model="form.fecha_limite"
                                    class="mt-1 block w-full"
                                />
                                <InputError :message="form.errors.fecha_limite" class="mt-2" />
                            </div>
                        </div>

                        <!-- Vinculador (OPCIONAL) -->
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <InputLabel value="Vincular a:" />
                                <span class="text-xs text-gray-400 italic">(Opcional - Déjalo vacío para crear una nota general)</span>
                            </div>
                            
                            <div v-if="!form.tarea_id" class="mt-1">
                                <button type="button" @click="openSearchModal" class="w-full flex justify-center gap-2 px-4 py-2 border border-dashed border-gray-400 rounded-md text-sm hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300 transition">
                                    <LinkIcon class="h-5 w-5" /> Buscar Proceso, Caso o Contrato
                                </button>
                            </div>
                            <div v-else class="mt-1 flex items-center justify-between p-3 border border-green-500 bg-green-50 dark:bg-gray-900 rounded-md">
                                <span class="font-medium text-green-800 dark:text-green-400">{{ selectedItemText }}</span>
                                <button @click.prevent="removeSelectedItem" type="button" class="text-red-500 hover:bg-red-50 p-1 rounded-full"><XCircleIcon class="h-5 w-5" /></button>
                            </div>
                            <InputError :message="form.errors.tarea_id || form.errors.tarea_type" class="mt-2" />
                        </div>

                        <!-- Descripción -->
                        <div>
                            <InputLabel for="descripcion" value="Instrucciones:" required />
                            <Textarea id="descripcion" v-model="form.descripcion" class="mt-1 block w-full" rows="3" placeholder="Detalla todo aquí..." />
                            <InputError :message="form.errors.descripcion" class="mt-2" />
                        </div>

                        <div class="flex justify-end">
                            <PrimaryButton :disabled="form.processing" :class="{ 'opacity-25': form.processing }">
                                {{ form.processing ? 'Enviando...' : 'Asignar Tarea / Nota' }}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>

                <!-- LISTA DE TAREAS -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <!-- Filtros -->
                    <div class="p-4 bg-gray-50 dark:bg-gray-800/50 flex gap-4">
                        <select v-model="filtroEstado" class="rounded-md border-gray-300 dark:bg-gray-900">
                            <option value="todos">Todos los Estados</option>
                            <option value="pendiente">Pendientes</option>
                            <option value="completada">Completadas</option>
                        </select>
                        <select v-model="filtroUsuario" class="rounded-md border-gray-300 dark:bg-gray-900">
                            <option value="todos">Todos los Usuarios</option>
                            <option v-for="user in usuarios" :key="user.id" :value="user.id">{{ user.name }}</option>
                        </select>
                    </div>

                    <!-- Tabla -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm text-left">
                            <thead class="bg-gray-50 dark:bg-gray-700 uppercase text-xs">
                                <tr>
                                    <th class="px-6 py-3">Semáforo</th>
                                    <th class="px-6 py-3">Tarea</th>
                                    <th class="px-6 py-3">Responsable</th>
                                    <th class="px-6 py-3">Vencimiento</th>
                                    <th class="px-6 py-3">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="tarea in tareas.data" :key="tarea.id" class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                    <td class="px-6 py-4">
                                        <!-- SEMÁFORO VISUAL -->
                                        <div class="flex flex-col items-center gap-1">
                                            <span class="px-3 py-1 rounded-full text-xs font-bold border whitespace-nowrap"
                                                :class="getSemaforoClasses(tarea.semaforo)">
                                                {{ getSemaforoLabel(tarea) }}
                                            </span>
                                            <span v-if="tarea.semaforo === 'vencida' && tarea.estado !== 'completada'" class="text-[10px] text-red-600 font-bold">
                                                ¡PENALIZAR!
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 max-w-xs">
                                        <p class="font-bold truncate" :title="tarea.titulo">{{ tarea.titulo }}</p>
                                        <p class="text-xs text-gray-500 truncate">{{ tarea.descripcion }}</p>
                                        
                                        <!-- Indicador visual si es una nota suelta -->
                                        <p v-if="!tarea.tarea_type" class="mt-1 inline-flex items-center text-[10px] bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded border border-gray-200">
                                            <DocumentTextIcon class="h-3 w-3 mr-1"/> Nota General
                                        </p>
                                        <!-- Indicador si está vinculada -->
                                        <p v-else class="mt-1 inline-flex items-center text-[10px] bg-indigo-50 text-indigo-600 px-1.5 py-0.5 rounded border border-indigo-100">
                                            <LinkIcon class="h-3 w-3 mr-1"/> Vinculada
                                        </p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            <span class="bg-indigo-100 text-indigo-700 rounded-full h-8 w-8 flex items-center justify-center font-bold text-xs">
                                                {{ getUserInitials(tarea.asignado_a.name) }}
                                            </span>
                                            <div>
                                                <p class="font-medium">{{ tarea.asignado_a.name }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-1">
                                            <ClockIcon v-if="tarea.fecha_limite" class="h-4 w-4 text-gray-400" />
                                            <span class="font-mono text-xs" :class="{'text-gray-400 italic': !tarea.fecha_limite}">
                                                {{ formatDateLocal(tarea.fecha_limite) }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <DangerButton @click="openDeleteModal(tarea)" small>Eliminar</DangerButton>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <Pagination v-if="tareas.links.length > 3" class="mt-6 p-4" :links="tareas.links" />
                </div>
            </div>
        </div>

        <!-- Modales Search y Delete (Sin cambios) -->
        <Modal :show="showSearchModal" @close="showSearchModal = false">
             <div class="p-6">
                <h2 class="text-lg font-medium">Buscar elemento</h2>
                <div class="mt-2 flex gap-2 border-b">
                    <button @click="changeSearchTab('proceso')" :class="{'border-b-2 border-indigo-500': currentSearchTab==='proceso'}" class="px-3 py-2">Procesos</button>
                    <button @click="changeSearchTab('caso')" :class="{'border-b-2 border-indigo-500': currentSearchTab==='caso'}" class="px-3 py-2">Casos</button>
                    <button @click="changeSearchTab('contrato')" :class="{'border-b-2 border-indigo-500': currentSearchTab==='contrato'}" class="px-3 py-2">Contratos</button>
                </div>
                <input v-model="searchQuery" placeholder="Buscar..." class="w-full mt-4 p-2 border rounded" />
                <div class="mt-2 max-h-48 overflow-y-auto">
                    <div v-for="item in searchResults" :key="item.id" @click="selectItem(item)" class="p-2 hover:bg-gray-100 cursor-pointer border-b">
                        {{ item.texto }}
                    </div>
                </div>
                <div class="mt-4 flex justify-end"><PrimaryButton @click="showSearchModal = false">Cerrar</PrimaryButton></div>
             </div>
        </Modal>

        <Modal :show="showDeleteModal" @close="showDeleteModal = false">
             <div class="p-6">
                <h2 class="text-lg font-bold text-red-600">¿Eliminar Tarea?</h2>
                <p class="mt-2">Esto no se puede deshacer.</p>
                <div class="mt-4 flex justify-end gap-2">
                    <PrimaryButton @click="showDeleteModal = false">Cancelar</PrimaryButton>
                    <DangerButton @click="submitDeleteTask">Sí, Eliminar</DangerButton>
                </div>
             </div>
        </Modal>

    </AuthenticatedLayout>
</template>