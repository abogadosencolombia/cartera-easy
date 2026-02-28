<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { 
    MagnifyingGlassIcon, 
    ArrowPathIcon, 
    FunnelIcon, 
    CalendarDaysIcon, 
    UserIcon,
    ExclamationTriangleIcon,
    CheckCircleIcon,
    InformationCircleIcon,
    ShieldCheckIcon,
    ClockIcon,
    ChevronDownIcon
} from '@heroicons/vue/24/outline';
import Pagination from '@/Components/Pagination.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DatePicker from '@/Components/DatePicker.vue';

const props = defineProps({
    eventos: Object, // Laravel Paginator Object
    usuarios: Array,
    filtros: Object,
});

// Estado local de los filtros
const filtroForm = ref({
    user_id: props.filtros.user_id || '',
    evento: props.filtros.evento || '',
    fecha_desde: props.filtros.fecha_desde || '',
    fecha_hasta: props.filtros.fecha_hasta || '',
});

// Aplicar filtros
const aplicarFiltros = () => {
    const query = {};
    // Solo enviamos lo que tenga valor para mantener la URL limpia
    for (const key in filtroForm.value) {
        if (filtroForm.value[key]) {
            query[key] = filtroForm.value[key];
        }
    }
    
    router.get(route('admin.auditoria.index'), query, {
        preserveState: true,
        replace: true,
    });
};

// Limpiar filtros
const limpiarFiltros = () => {
    filtroForm.value = {
        user_id: '',
        evento: '',
        fecha_desde: '',
        fecha_hasta: '',
    };
    aplicarFiltros();
};

// Formato de fecha amigable y legible
const formatDateTime = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return new Intl.DateTimeFormat('es-CO', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit', hour12: true
    }).format(date);
};

// Configuración visual según criticidad (Colores e Iconos)
const getCriticidadConfig = (nivel) => {
    const n = nivel?.toLowerCase() || 'baja';
    switch (n) {
        case 'alta':
            return {
                classes: 'bg-red-50 text-red-700 border-red-200 ring-red-600/20',
                icon: ExclamationTriangleIcon,
                label: 'Crítico'
            };
        case 'media':
            return {
                classes: 'bg-amber-50 text-amber-700 border-amber-200 ring-amber-600/20',
                icon: InformationCircleIcon,
                label: 'Importante'
            };
        default: // baja
            return {
                classes: 'bg-blue-50 text-blue-700 border-blue-200 ring-blue-600/20',
                icon: CheckCircleIcon,
                label: 'Normal'
            };
    }
};
</script>

<template>
    <Head title="Auditoría del Sistema" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <ShieldCheckIcon class="h-6 w-6 text-indigo-600 dark:text-indigo-400" />
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Auditoría Global y Seguridad
                </h2>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- TARJETA DE FILTROS -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-xl border border-gray-100 dark:border-gray-700">
                    <div class="p-6">
                        <div class="flex items-center gap-2 mb-6 pb-4 border-b border-gray-100 dark:border-gray-700">
                            <FunnelIcon class="h-5 w-5 text-gray-400" />
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Buscador Avanzado de Eventos</h3>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <!-- Filtro Usuario -->
                            <div class="space-y-2">
                                <InputLabel for="filtro_usuario" value="¿Quién realizó la acción?" />
                                <Dropdown align="left" width="full">
                                    <template #trigger>
                                        <button type="button" class="mt-1 flex w-full items-center justify-between gap-2 rounded-md border border-gray-300 dark:border-gray-700 dark:bg-gray-900 bg-white px-3 py-2 text-sm shadow-sm hover:border-indigo-500 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all cursor-pointer dark:text-gray-300">
                                            <div class="flex items-center gap-2 truncate">
                                                <UserIcon class="h-5 w-5 text-gray-400" />
                                                <span class="truncate">{{ filtroForm.user_id ? usuarios.find(u => u.id === filtroForm.user_id)?.name : 'Todos los usuarios' }}</span>
                                            </div>
                                            <ChevronDownIcon class="h-4 w-4 text-gray-400" />
                                        </button>
                                    </template>
                                    <template #content>
                                        <div class="py-1 bg-white dark:bg-gray-800 max-h-60 overflow-y-auto">
                                            <button @click="filtroForm.user_id = ''" class="block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700" :class="{ 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-400 font-bold': filtroForm.user_id === '' }">Todos los usuarios</button>
                                            <button v-for="user in usuarios" :key="user.id" @click="filtroForm.user_id = user.id" class="block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700" :class="{ 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-400 font-bold': filtroForm.user_id === user.id }">
                                                {{ user.name }}
                                            </button>
                                        </div>
                                    </template>
                                </Dropdown>
                            </div>

                            <!-- Filtro Evento -->
                            <div class="space-y-2">
                                <InputLabel for="filtro_evento" value="¿Qué acción buscas?" />
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" />
                                    </div>
                                    <TextInput 
                                        type="text" 
                                        v-model="filtroForm.evento" 
                                        id="filtro_evento" 
                                        placeholder="Ej: Eliminar, Login, Subir..." 
                                        class="pl-10 mt-1 block w-full"
                                    />
                                </div>
                            </div>

                            <!-- Filtro Fecha Desde -->
                            <div class="space-y-2">
                                <InputLabel for="fecha_desde" value="Desde la fecha" />
                                <DatePicker v-model="filtroForm.fecha_desde" id="fecha_desde" class="w-full" />
                            </div>

                            <!-- Filtro Fecha Hasta -->
                            <div class="space-y-2">
                                <InputLabel for="fecha_hasta" value="Hasta la fecha" />
                                <DatePicker v-model="filtroForm.fecha_hasta" id="fecha_hasta" class="w-full" />
                            </div>
                        </div>

                        <div class="mt-8 flex flex-col sm:flex-row justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                            <SecondaryButton @click="limpiarFiltros" class="justify-center">
                                <ArrowPathIcon class="h-4 w-4 mr-2" />
                                Limpiar Filtros
                            </SecondaryButton>
                            <PrimaryButton @click="aplicarFiltros" class="justify-center">
                                <MagnifyingGlassIcon class="h-4 w-4 mr-2" />
                                Buscar Eventos
                            </PrimaryButton>
                        </div>
                    </div>
                </div>

                <!-- RESULTADOS -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-xl border border-gray-100 dark:border-gray-700">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                Historial de Movimientos
                                <span class="ml-2 text-xs font-normal text-gray-500 bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded-full">
                                    {{ eventos.total }} registros encontrados
                                </span>
                            </h3>
                        </div>

                        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700/50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Fecha y Hora</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Usuario</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Evento</th>
                                        <th scope="col" class="px-6 py-3 text-center text-xs font-bold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Impacto</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Detalle de la Acción</th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-bold text-gray-500 dark:text-gray-300 uppercase tracking-wider">IP Origen</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr v-if="eventos.data.length === 0">
                                        <td colspan="6" class="px-6 py-16 text-center">
                                            <div class="flex flex-col items-center justify-center text-gray-400">
                                                <ShieldCheckIcon class="h-12 w-12 mb-3 text-gray-300" />
                                                <p class="text-base font-medium text-gray-900 dark:text-gray-100">No se encontraron registros</p>
                                                <p class="text-sm mt-1">Intenta ajustar los filtros de búsqueda.</p>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-for="evento in eventos.data" :key="evento.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                        <!-- Fecha -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center text-sm text-gray-600 dark:text-gray-300">
                                                <ClockIcon class="h-4 w-4 mr-2 text-gray-400" />
                                                {{ formatDateTime(evento.created_at) }}
                                            </div>
                                        </td>
                                        
                                        <!-- Usuario -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="h-8 w-8 rounded-full bg-indigo-100 dark:bg-indigo-900/50 flex items-center justify-center text-indigo-600 dark:text-indigo-300 font-bold text-xs uppercase mr-3">
                                                    {{ evento.usuario ? evento.usuario.name.substring(0,2) : 'SI' }}
                                                </div>
                                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                    {{ evento.usuario ? evento.usuario.name : 'Sistema' }}
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Evento -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="text-xs font-mono bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 px-2 py-1 rounded border border-gray-200 dark:border-gray-600">
                                                {{ evento.evento }}
                                            </span>
                                        </td>

                                        <!-- Criticidad -->
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <span 
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border ring-1 ring-inset gap-1"
                                                :class="getCriticidadConfig(evento.criticidad).classes"
                                            >
                                                <component :is="getCriticidadConfig(evento.criticidad).icon" class="h-3 w-3" />
                                                {{ getCriticidadConfig(evento.criticidad).label }}
                                            </span>
                                        </td>

                                        <!-- Descripción -->
                                        <td class="px-6 py-4">
                                            <p class="text-sm text-gray-700 dark:text-gray-300 max-w-md break-words leading-relaxed">
                                                {{ evento.descripcion_breve }}
                                            </p>
                                        </td>

                                        <!-- IP -->
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500 font-mono">
                                            {{ evento.direccion_ip }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Paginación -->
                        <div class="mt-6">
                            <Pagination :links="eventos.links" />
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>