<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { MagnifyingGlassIcon, ArrowPathIcon, EyeIcon } from '@heroicons/vue/24/outline';
import Pagination from '@/Components/Pagination.vue'; // Asumo que tienes un componente de paginación
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    eventos: Object, // Laravel Paginator Object
    usuarios: Array,
    filtros: Object,
});

// Formulario reactivo para los filtros
const filtroForm = ref({
    user_id: props.filtros.user_id || '',
    evento: props.filtros.evento || '',
    fecha_desde: props.filtros.fecha_desde || '',
    fecha_hasta: props.filtros.fecha_hasta || '',
});

// Función para aplicar filtros
const aplicarFiltros = () => {
    const query = { ...filtroForm.value };
    // Elimina los filtros vacíos para no ensuciar la URL
    Object.keys(query).forEach(key => {
        if (!query[key]) {
            delete query[key];
        }
    });
    router.get(route('admin.auditoria.index'), query, {
        preserveState: true,
        replace: true,
    });
};

// Función para limpiar los filtros
const limpiarFiltros = () => {
    filtroForm.value = { user_id: '', evento: '', fecha_desde: '', fecha_hasta: '' };
    aplicarFiltros();
};

// Función para formatear fechas
const formatDateTime = (dateString) => {
    if (!dateString) return 'N/A';
    const options = { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' };
    return new Date(dateString).toLocaleString('es-CO', options);
};

// Clases de estilo para la criticidad del evento
const criticidadClasses = {
    alta: 'bg-red-100 text-red-800 border-red-500',
    media: 'bg-yellow-100 text-yellow-800 border-yellow-500',
    baja: 'bg-blue-100 text-blue-800 border-blue-500',
};

</script>

<template>
    <Head title="Auditoría del Sistema" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Panel de Auditoría Global
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Sección de Filtros -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Filtrar Eventos</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <div>
                                <label for="filtro_usuario" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Usuario</label>
                                <select v-model="filtroForm.user_id" id="filtro_usuario" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="">Todos</option>
                                    <option v-for="user in usuarios" :key="user.id" :value="user.id">{{ user.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label for="filtro_evento" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tipo de Evento</label>
                                <input type="text" v-model="filtroForm.evento" id="filtro_evento" placeholder="Ej: LOGIN, DOCUMENTO" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div>
                                <label for="fecha_desde" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Desde</label>
                                <input type="date" v-model="filtroForm.fecha_desde" id="fecha_desde" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div>
                                <label for="fecha_hasta" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Hasta</label>
                                <input type="date" v-model="filtroForm.fecha_hasta" id="fecha_hasta" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                        </div>
                        <div class="mt-6 flex justify-end space-x-4">
                            <SecondaryButton @click="limpiarFiltros" class="flex items-center"><ArrowPathIcon class="h-5 w-5 mr-2" />Limpiar</SecondaryButton>
                            <PrimaryButton @click="aplicarFiltros" class="flex items-center"><MagnifyingGlassIcon class="h-5 w-5 mr-2" />Buscar</PrimaryButton>
                        </div>
                    </div>
                </div>

                <!-- Tabla de Resultados -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha y Hora</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Usuario</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Evento</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Criticidad</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Descripción</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr v-if="eventos.data.length === 0">
                                        <td colspan="6" class="px-6 py-10 text-center text-gray-500">No se encontraron eventos con los filtros aplicados.</td>
                                    </tr>
                                    <tr v-for="evento in eventos.data" :key="evento.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ formatDateTime(evento.created_at) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">{{ evento.usuario ? evento.usuario.name : 'Sistema' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-mono">{{ evento.evento }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span :class="['px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full capitalize border', criticidadClasses[evento.criticidad]]">
                                                {{ evento.criticidad }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300 max-w-md">{{ evento.descripcion_breve }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ evento.direccion_ip }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Paginación -->
                        <Pagination class="mt-6" :links="eventos.links" />

                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
