<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import Pagination from '@/Components/Pagination.vue';

// Importación de iconos
import {
    BellAlertIcon,
    InboxIcon,
    EyeIcon,
    CheckCircleIcon,
    CheckBadgeIcon,
    ClockIcon,
    CalendarDaysIcon,
    InformationCircleIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    notificaciones: Object,
    filtros: Object,
    tipos_alerta: Array,
});

// --- Lógica de Acciones ---
const marcarComoLeida = (notificacionId) => {
    router.patch(route('notificaciones.leer', notificacionId), {}, {
        preserveScroll: true,
        only: ['notificaciones'],
    });
};

const marcarComoAtendida = (notificacionId) => {
    router.patch(route('notificaciones.atender', notificacionId), {}, {
        preserveScroll: true,
        only: ['notificaciones'],
    });
};

// --- Lógica de Filtros ---
const localFiltros = ref({
    leido: props.filtros.leido || '',
    tipo: props.filtros.tipo || '',
});

watch(localFiltros, (newFilters) => {
    router.get(route('notificaciones.index'), newFilters, {
        preserveState: true,
        replace: true,
    });
}, { deep: true });


// --- Ayudantes de Estilo y Formato ---
const alertaMeta = {
    'vencimiento_termino': { icon: ClockIcon, color: 'text-red-600', bgColor: 'bg-red-100', borderColor: 'border-l-red-500' },
    'tarea_asignada': { icon: CalendarDaysIcon, color: 'text-blue-600', bgColor: 'bg-blue-100', borderColor: 'border-l-blue-500' },
    'recordatorio': { icon: BellAlertIcon, color: 'text-yellow-600', bgColor: 'bg-yellow-100', borderColor: 'border-l-yellow-500' },
    'default': { icon: InformationCircleIcon, color: 'text-gray-600', bgColor: 'bg-gray-100', borderColor: 'border-l-gray-500' }
};

// --- FUNCIÓN MEJORADA ---
const getAlertaMeta = (tipo) => {
    // Si el tipo es nulo o no existe, devuelve el valor por defecto de forma segura.
    if (!tipo || !alertaMeta[tipo]) {
        return alertaMeta['default'];
    }
    return alertaMeta[tipo];
};


const formatTime = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleTimeString('es-CO', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
    }).toLowerCase();
};

// --- Lógica para Agrupar Notificaciones por Fecha ---
const groupedNotifications = computed(() => {
    const groups = { Hoy: [], Ayer: [], 'Esta semana': [], 'Este mes': [], 'Más antiguas': [] };
    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const yesterday = new Date(today); yesterday.setDate(yesterday.getDate() - 1);
    const oneWeekAgo = new Date(today); oneWeekAgo.setDate(oneWeekAgo.getDate() - 7);
    const oneMonthAgo = new Date(today); oneMonthAgo.setMonth(oneMonthAgo.getMonth() - 1);

    for (const notif of props.notificaciones.data) {
        const notifDate = new Date(notif.created_at);
        const notifDay = new Date(notifDate.getFullYear(), notifDate.getMonth(), notifDate.getDate());

        if (notifDay.getTime() === today.getTime()) groups.Hoy.push(notif);
        else if (notifDay.getTime() === yesterday.getTime()) groups.Ayer.push(notif);
        else if (notifDate > oneWeekAgo) groups['Esta semana'].push(notif);
        else if (notifDate > oneMonthAgo) groups['Este mes'].push(notif);
        else groups['Más antiguas'].push(notif);
    }

    return Object.entries(groups)
        .filter(([, value]) => value.length > 0)
        .map(([title, notifications]) => ({ title, notifications }));
});

</script>

<template>
    <Head title="Bandeja de Notificaciones" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Centro de Notificaciones
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- SECCIÓN DE FILTROS -->
                <div class="p-4 bg-white dark:bg-gray-800 shadow-sm rounded-lg border dark:border-gray-700">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="filtro_leido" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Estado</label>
                            <select v-model="localFiltros.leido" id="filtro_leido" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 transition">
                                <option value="">Todos</option>
                                <option value="no">No Leídas</option>
                                <option value="si">Leídas</option>
                            </select>
                        </div>
                        <div>
                            <label for="filtro_tipo" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tipo de Alerta</label>
                            <select v-model="localFiltros.tipo" id="filtro_tipo" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-md shadow-sm capitalize focus:ring-indigo-500 focus:border-indigo-500 transition">
                                <option value="">Todos</option>
                                <!-- Se añade una comprobación aquí también por seguridad -->
                                <option v-for="tipo in tipos_alerta" :key="tipo" :value="tipo">{{ tipo ? tipo.replace(/_/g, ' ') : '' }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- LÍNEA DE TIEMPO DE NOTIFICACIONES -->
                <div v-if="notificaciones.data.length > 0">
                    <div v-for="group in groupedNotifications" :key="group.title" class="mb-8">
                        <h3 class="px-2 text-xs font-bold uppercase text-gray-500 dark:text-gray-400 tracking-wider mb-3">
                            {{ group.title }}
                        </h3>
                        <div class="space-y-4">
                             <!-- TARJETA DE NOTIFICACIÓN EN LA LÍNEA DE TIEMPO -->
                            <div v-for="notificacion in group.notifications" :key="notificacion.id" class="relative flex items-start space-x-4 pl-12 group">
                                <!-- La Línea y el Punto de la Timeline -->
                                <div class="absolute left-6 top-7 bottom-0 w-0.5 bg-gray-200 dark:bg-gray-700"></div>
                                <div class="absolute left-4 top-5 flex items-center justify-center h-5 w-5 rounded-full" :class="[getAlertaMeta(notificacion.tipo_alerta).bgColor, { 'ring-4 ring-blue-500/30': !notificacion.leido }]">
                                     <component :is="getAlertaMeta(notificacion.tipo_alerta).icon" class="h-3 w-3" :class="getAlertaMeta(notificacion.tipo_alerta).color" />
                                </div>

                                <div class="w-full bg-white dark:bg-gray-800 rounded-lg shadow-sm border dark:border-gray-700 border-l-4 transition-all duration-300 group-hover:shadow-md group-hover:-translate-y-px"
                                     :class="[getAlertaMeta(notificacion.tipo_alerta).borderColor, { 'opacity-60': notificacion.atendida_en }]">

                                     <!-- Encabezado de la Tarjeta -->
                                    <div class="px-4 py-3 flex justify-between items-center bg-gray-50 dark:bg-gray-800/50 rounded-t-md">
                                        <!-- LÍNEA CORREGIDA -->
                                        <p class="text-sm font-bold capitalize" :class="getAlertaMeta(notificacion.tipo_alerta).color">
                                            {{ notificacion.tipo_alerta ? notificacion.tipo_alerta.replace(/_/g, ' ') : 'Notificación' }}
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ formatTime(notificacion.created_at) }}</p>
                                    </div>

                                    <!-- Cuerpo de la Tarjeta -->
                                    <div class="p-4">
                                        <p class="text-gray-700 dark:text-gray-200 break-words">{{ notificacion.mensaje }}</p>
                                    </div>

                                    <!-- Pie de la Tarjeta -->
                                    <div class="px-4 py-2 bg-gray-50 dark:bg-gray-800/50 rounded-b-md flex items-center justify-between">
                                        <Link :href="route('casos.show', notificacion.caso_id)" class="text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                                            Ver Caso #{{ notificacion.caso_id }}
                                        </Link>
                                        <div class="flex items-center gap-2">
                                            <button v-if="!notificacion.leido" @click="marcarComoLeida(notificacion.id)" title="Marcar como leída"
                                                class="p-1.5 rounded-full text-gray-500 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
                                                <EyeIcon class="h-5 w-5" />
                                            </button>
                                            <button v-if="notificacion.leido && !notificacion.atendida_en" @click="marcarComoAtendida(notificacion.id)" title="Marcar como atendida"
                                                class="p-1.5 rounded-full text-gray-500 hover:bg-green-100 dark:hover:bg-green-800/50 hover:text-green-600 transition-colors">
                                                <CheckBadgeIcon class="h-5 w-5" />
                                            </button>
                                            <div v-if="notificacion.atendida_en" class="flex items-center text-xs text-green-600 dark:text-green-400 font-semibold">
                                                <CheckCircleIcon class="h-4 w-4 mr-1.5" />
                                                <span>Atendida</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ESTADO VACÍO -->
                <div v-else class="text-center py-20 px-6 bg-white dark:bg-gray-800 rounded-lg shadow-sm border dark:border-gray-700">
                    <InboxIcon class="h-20 w-20 mx-auto text-gray-300 dark:text-gray-600" />
                    <h3 class="mt-4 text-xl font-semibold text-gray-800 dark:text-gray-200">Todo en orden</h3>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No hay notificaciones por ahora. ¡Buen trabajo!</p>
                </div>

                <!-- Paginación -->
                <div v-if="notificaciones.data.length > 0" class="mt-6">
                    <Pagination :links="notificaciones.links" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>