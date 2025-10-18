<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import Modal from '@/Components/Modal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Dropdown from '@/Components/Dropdown.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import { debounce, pickBy } from 'lodash'; // <-- Importamos pickBy
import { ScaleIcon, PlusIcon, ArrowDownTrayIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    incidentes: Object,
    filters: Object,
    usuarios: Array,
});

// --- Lógica para los filtros ---
const form = useForm({
    search: props.filters.search || '',
    estado: props.filters.estado || '',
    responsable_id: props.filters.responsable_id || '',
});

// ===== SOLUCIÓN DEFINITIVA AL BUCLE =====
// Este es el método recomendado por Inertia.
// Observa el formulario y, después de 300ms de inactividad,
// envía solo los filtros que tienen un valor.
watch(() => form.data(), debounce(function() {
    form.get(route('admin.incidentes-juridicos.index'), pickBy(form.data()), {
        preserveState: true,
        replace: true,
    });
}, 300), { deep: true });


// --- Lógica para el modal de eliminación ---
const confirmingIncidentDeletion = ref(false);
const incidentToDelete = ref(null);
const confirmIncidentDeletion = (incidente) => {
    incidentToDelete.value = incidente;
    confirmingIncidentDeletion.value = true;
};
const closeModal = () => {
    confirmingIncidentDeletion.value = false;
    incidentToDelete.value = null;
};
const deleteIncident = () => {
    router.delete(route('admin.incidentes-juridicos.destroy', incidentToDelete.value.id), {
        onSuccess: () => closeModal(),
    });
};

// --- URL de exportación con Computed ---
const exportUrl = computed(() => {
    const params = new URLSearchParams(pickBy(form.data())).toString();
    return route('admin.incidentes-juridicos.exportar') + '?' + params;
});

// --- Funciones de formato ---
const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    const options = { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' };
    return new Date(dateString).toLocaleDateString('es-CO', options);
};
const estadoClass = (estado) => {
    const classes = {
        pendiente: 'bg-yellow-100 text-yellow-800',
        en_revision: 'bg-blue-100 text-blue-800',
        resuelto: 'bg-green-100 text-green-800',
        archivado: 'bg-gray-100 text-gray-800',
    };
    return classes[estado] || 'bg-gray-200';
};
</script>

<template>
    <Head title="Gestión de Incidentes Jurídicos" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight flex items-center">
                    <ScaleIcon class="h-6 w-6 inline-block mr-2"/>
                    Gestión de Incidentes Jurídicos
                </h2>
                <div class="flex items-center space-x-2">
                    <Dropdown align="right" width="48">
                        <template #trigger>
                            <SecondaryButton>
                                Acciones
                                <svg class="ms-2 -me-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                </svg>
                            </SecondaryButton>
                        </template>

                        <template #content>
                            <a :href="`${exportUrl}&format=xlsx`" class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 transition duration-150 ease-in-out">
                                <div class="flex items-center">
                                    <ArrowDownTrayIcon class="h-4 w-4 mr-2" />
                                    Exportar a Excel
                                </div>
                            </a>
                            <a :href="`${exportUrl}&format=pdf`" target="_blank" class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 transition duration-150 ease-in-out">
                                <div class="flex items-center">
                                    <ArrowDownTrayIcon class="h-4 w-4 mr-2" />
                                    Exportar a PDF
                                </div>
                            </a>
                        </template>
                    </Dropdown>

                    <Link :href="route('admin.incidentes-juridicos.create')" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        <PlusIcon class="h-4 w-4 mr-2"/>
                        Registrar Incidente
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <div class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label for="search" class="block text-sm font-medium text-gray-700">Buscar por Asunto</label>
                                <input type="text" v-model="form.search" id="search" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="Ej: derecho de petición...">
                            </div>
                            <div>
                                <label for="estado" class="block text-sm font-medium text-gray-700">Estado</label>
                                <select v-model="form.estado" id="estado" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">Todos</option>
                                    <option value="pendiente">Pendiente</option>
                                    <option value="en_revision">En Revisión</option>
                                    <option value="resuelto">Resuelto</option>
                                    <option value="archivado">Archivado</option>
                                </select>
                            </div>
                            <div>
                                <label for="responsable_id" class="block text-sm font-medium text-gray-700">Responsable</label>
                                <select v-model="form.responsable_id" id="responsable_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">Todos</option>
                                    <option v-for="usuario in usuarios" :key="usuario.id" :value="usuario.id">{{ usuario.name }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Asunto</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Responsable</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha de Registro</th>
                                        <th scope="col" class="relative px-6 py-3">
                                            <span class="sr-only">Acciones</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-if="incidentes.data.length === 0">
                                        <td colspan="5" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                            No se encontraron incidentes con los filtros actuales.
                                        </td>
                                    </tr>
                                    <tr v-for="incidente in incidentes.data" :key="incidente.id">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">{{ incidente.asunto }}</div>
                                            <div class="text-sm text-gray-500 capitalize">{{ incidente.origen }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ incidente.responsable ? incidente.responsable.name : 'No asignado' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full" :class="estadoClass(incidente.estado)">
                                                {{ incidente.estado ? incidente.estado.replace('_', ' ') : 'N/A' }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ formatDate(incidente.fecha_registro) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div class="flex justify-end items-center space-x-2">
                                                <Link :href="route('admin.incidentes-juridicos.show', incidente.id)"
                                                    class="inline-flex items-center px-3 py-1 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none disabled:opacity-25 transition ease-in-out duration-150">
                                                    Ver
                                                </Link>
                                                
                                                <DangerButton @click="confirmIncidentDeletion(incidente)" 
                                                            class="!px-3 !py-1 !text-xs">
                                                    Eliminar
                                                </DangerButton>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">
                            <Pagination :links="incidentes.links" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Modal :show="confirmingIncidentDeletion" @close="closeModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    ¿Estás seguro de que deseas eliminar este incidente?
                </h2>
                <p class="mt-1 text-sm text-gray-600" v-if="incidentToDelete">
                    Se eliminará permanentemente el incidente "<span class="font-medium">{{ incidentToDelete.asunto }}</span>" y todos sus tickets y archivos asociados. Esta acción no se puede deshacer.
                </p>
                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="closeModal"> Cancelar </SecondaryButton>
                    <DangerButton class="ms-3" @click="deleteIncident">
                        Sí, Eliminar Incidente
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
